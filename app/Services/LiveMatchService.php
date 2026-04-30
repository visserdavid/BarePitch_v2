<?php

declare(strict_types=1);

namespace BarePitch\Services;

use BarePitch\Core\Database;
use BarePitch\Core\Exceptions\DomainException;
use BarePitch\Domain\EventType;
use BarePitch\Domain\EventOutcome;
use BarePitch\Domain\MatchStatus;
use BarePitch\Domain\PeriodKey;
use BarePitch\Domain\TeamSide;
use BarePitch\Domain\ScoreCalculator;
use BarePitch\Repositories\MatchRepository;
use BarePitch\Repositories\SelectionRepository;
use BarePitch\Repositories\EventRepository;
use BarePitch\Repositories\LockRepository;

class LiveMatchService
{
    public function __construct(
        private readonly MatchRepository     $matches,
        private readonly SelectionRepository $selections,
        private readonly EventRepository     $events,
        private readonly LockRepository      $locks,
        private readonly AuditService        $audit,
    ) {}

    /**
     * Starts a prepared match: transitions to 'active', creates first period, marks starters as active on field.
     */
    public function startMatch(array $user, array $match): void
    {
        if ($match['status'] !== MatchStatus::Prepared->value) {
            throw new DomainException('Only a prepared match can be started.');
        }

        Database::beginTransaction();
        try {
            $this->matches->updateStatus((int) $match['id'], MatchStatus::Active->value, 'regular_time');
            $this->matches->updateScore((int) $match['id'], 0, 0);

            $this->matches->createPeriod([
                'match_id'                    => $match['id'],
                'period_key'                  => PeriodKey::Regular1->value,
                'sort_order'                  => 1,
                'started_at'                  => date('Y-m-d H:i:s'),
                'configured_duration_minutes' => (int) $match['regular_half_duration_minutes'],
            ]);

            // Mark starters as active on field
            $starters = $this->selections->findStartersForMatch((int) $match['id']);
            foreach ($starters as $starter) {
                $this->selections->update((int) $starter['id'], ['is_active_on_field' => 1]);
            }

            $this->audit->log(
                userId:     (int) $user['id'],
                entityType: 'match',
                entityId:   (int) $match['id'],
                actionKey:  'match.started',
                matchId:    (int) $match['id'],
            );

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Registers a goal event and recalculates the score.
     */
    public function registerGoal(array $user, array $match, array $data): int
    {
        if ($match['status'] !== MatchStatus::Active->value) {
            throw new DomainException('Goals can only be registered for an active match.');
        }

        // Validate team_side
        $side = $data['team_side'] ?? TeamSide::Own->value;
        if (!in_array($side, [TeamSide::Own->value, TeamSide::Opponent->value], true)) {
            throw new DomainException('Invalid team side for goal registration.');
        }

        // Get current period (regular_1 or regular_2)
        $periods = $this->matches->findPeriods((int) $match['id']);
        $activePeriod = null;
        foreach (array_reverse($periods) as $p) {
            if ($p['started_at'] !== null && $p['ended_at'] === null) {
                $activePeriod = $p;
                break;
            }
        }

        Database::beginTransaction();
        try {
            $eventId = $this->events->create([
                'match_id'             => $match['id'],
                'period_id'            => $activePeriod ? $activePeriod['id'] : null,
                'event_type'           => EventType::Goal->value,
                'team_side'            => $side,
                'player_selection_id'  => $data['player_selection_id'] ?? null,
                'assist_selection_id'  => $data['assist_selection_id'] ?? null,
                'zone_code'            => $data['zone_code'] ?? null,
                'outcome'              => EventOutcome::None->value,
                'minute_display'       => $data['minute_display'] ?? null,
                'match_second'         => $data['match_second'] ?? null,
                'created_by_user_id'   => $user['id'],
            ]);

            $this->recalculateScore((int) $match['id']);

            $this->audit->log(
                userId:     (int) $user['id'],
                entityType: 'match_event',
                entityId:   $eventId,
                actionKey:  'match.goal_registered',
                matchId:    (int) $match['id'],
            );

            Database::commit();
            return $eventId;
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Score recalculation. MUST be called inside the caller's transaction.
     * Fetches score events and runs ScoreCalculator — never blind-increments.
     */
    public function recalculateScore(int $matchId): void
    {
        $scoreEvents = $this->events->getScoreEvents($matchId);
        $score       = ScoreCalculator::calculate($scoreEvents);
        $this->matches->updateScore($matchId, $score['home'], $score['away']);
    }

    /**
     * Ends a period (sets ended_at).
     */
    public function endPeriod(array $user, array $match, int $periodId): void
    {
        if ($match['status'] !== MatchStatus::Active->value) {
            throw new DomainException('Periods can only be ended for an active match.');
        }

        $periods = $this->matches->findPeriods((int) $match['id']);
        $period = null;
        foreach ($periods as $p) {
            if ((int) $p['id'] === $periodId) {
                $period = $p;
                break;
            }
        }

        if ($period === null) {
            throw new DomainException('Period not found for this match.');
        }
        if ($period['started_at'] === null) {
            throw new DomainException('Period has not been started yet.');
        }
        if ($period['ended_at'] !== null) {
            throw new DomainException('Period has already ended.');
        }

        $newActivePhase = $period['period_key'] === PeriodKey::Regular1->value ? 'halftime' : 'none';

        Database::beginTransaction();
        try {
            $this->matches->updatePeriod($periodId, ['ended_at' => date('Y-m-d H:i:s')]);
            $this->matches->update((int) $match['id'], ['active_phase' => $newActivePhase]);
            $this->audit->log(
                userId:     (int) $user['id'],
                entityType: 'match_period',
                entityId:   $periodId,
                actionKey:  'match.period_ended',
                matchId:    (int) $match['id'],
            );
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Starts the second half (creates regular_2 period).
     */
    public function startSecondHalf(array $user, array $match): void
    {
        if ($match['status'] !== MatchStatus::Active->value) {
            throw new DomainException('Cannot start second half: match is not active.');
        }

        $period1 = $this->matches->findPeriodByKey((int) $match['id'], PeriodKey::Regular1->value);
        if ($period1 === null || $period1['ended_at'] === null) {
            throw new DomainException('First half must be ended before starting the second half.');
        }

        $period2 = $this->matches->findPeriodByKey((int) $match['id'], PeriodKey::Regular2->value);
        if ($period2 !== null) {
            throw new DomainException('Second half has already been started.');
        }

        Database::beginTransaction();
        try {
            $this->matches->createPeriod([
                'match_id'                    => $match['id'],
                'period_key'                  => PeriodKey::Regular2->value,
                'sort_order'                  => 2,
                'started_at'                  => date('Y-m-d H:i:s'),
                'configured_duration_minutes' => (int) $match['regular_half_duration_minutes'],
            ]);
            $this->matches->update((int) $match['id'], ['active_phase' => 'regular_time']);
            $this->audit->log(
                userId:     (int) $user['id'],
                entityType: 'match',
                entityId:   (int) $match['id'],
                actionKey:  'match.second_half_started',
                matchId:    (int) $match['id'],
            );
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Finishes a match. Transitions active → finished.
     * Requires at least one period to have ended (can't finish without playing).
     */
    public function finishMatch(array $user, array $match): void
    {
        if ($match['status'] !== MatchStatus::Active->value) {
            throw new DomainException('Only an active match can be finished.');
        }

        // At least one period must have ended
        $periods = $this->matches->findPeriods((int) $match['id']);
        $hasEndedPeriod = array_filter($periods, fn($p) => $p['ended_at'] !== null);
        if (empty($hasEndedPeriod)) {
            throw new DomainException('At least one period must be ended before finishing the match.');
        }

        Database::beginTransaction();
        try {
            $this->matches->updateStatus((int) $match['id'], MatchStatus::Finished->value, 'finished');
            $this->matches->update((int) $match['id'], ['finished_at' => date('Y-m-d H:i:s')]);
            $this->locks->release((int) $match['id']);

            $this->audit->log(
                userId:     (int) $user['id'],
                entityType: 'match',
                entityId:   (int) $match['id'],
                actionKey:  'match.finished',
                matchId:    (int) $match['id'],
            );

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }
    }
}

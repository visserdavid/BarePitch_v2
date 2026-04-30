<?php

declare(strict_types=1);

namespace BarePitch\Services;

use BarePitch\Core\Database;
use BarePitch\Core\Exceptions\DomainException;
use BarePitch\Core\Exceptions\ValidationException;
use BarePitch\Domain\AttendanceStatus;
use BarePitch\Domain\MatchStatus;
use BarePitch\Domain\PreparationChecker;
use BarePitch\Repositories\MatchRepository;
use BarePitch\Repositories\SelectionRepository;
use BarePitch\Repositories\LineupRepository;
use BarePitch\Repositories\TeamRepository;

class MatchPreparationService
{
    public function __construct(
        private readonly MatchRepository     $matches,
        private readonly SelectionRepository $selections,
        private readonly LineupRepository    $lineup,
        private readonly TeamRepository      $teams,
        private readonly AuditService        $audit,
    ) {}

    /**
     * Saves attendance for a match. Allowed for planned/prepared status.
     * $playerAttendance: array of ['player_id' => int, 'status' => string, 'context_id' => int|null]
     */
    public function saveAttendance(array $user, array $match, array $playerAttendance): void
    {
        $this->requirePreparable($match);

        Database::beginTransaction();
        try {
            $this->selections->upsertAttendance((int) $match['id'], $playerAttendance);
            $this->audit->log(
                userId:     (int) $user['id'],
                entityType: 'match',
                entityId:   (int) $match['id'],
                actionKey:  'match.attendance_saved',
                matchId:    (int) $match['id'],
            );
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Saves the lineup (replaces all existing lineup slots for the match).
     * $slots: array of ['match_selection_id' => int, 'formation_position_id' => int|null,
     *                   'grid_row' => int|null, 'grid_col' => int|null]
     * Bench players have null formation_position_id/grid_row/grid_col.
     */
    public function saveLineup(array $user, array $match, array $slots): void
    {
        $this->requirePreparable($match);

        // Validate: no duplicate formation_position_id (for field players)
        $positionIds = array_filter(array_column($slots, 'formation_position_id'));
        if (count($positionIds) !== count(array_unique($positionIds))) {
            throw new ValidationException(['lineup' => 'Each formation position can only be assigned once.']);
        }

        // Validate: no duplicate match_selection_id
        $selectionIds = array_column($slots, 'match_selection_id');
        if (count($selectionIds) !== count(array_unique($selectionIds))) {
            throw new ValidationException(['lineup' => 'Each player can only appear once in the lineup.']);
        }

        Database::beginTransaction();
        try {
            $this->lineup->replaceForMatch((int) $match['id'], $slots);
            $this->audit->log(
                userId:     (int) $user['id'],
                entityType: 'match',
                entityId:   (int) $match['id'],
                actionKey:  'match.lineup_saved',
                matchId:    (int) $match['id'],
            );
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Confirms preparation and transitions match from planned → prepared.
     * Runs PreparationChecker; throws ValidationException with errors if requirements not met.
     */
    public function confirmPreparation(array $user, array $match): void
    {
        $this->requirePreparable($match);

        // Load data for PreparationChecker
        $selections       = $this->selections->findByMatch((int) $match['id']);
        $lineupSlots      = $this->lineup->findByMatch((int) $match['id']);
        $formationPositions = [];

        // Detect which formation is in use from the lineup slots
        $formationPositionIds = array_filter(array_column($lineupSlots, 'formation_position_id'));
        if (!empty($formationPositionIds)) {
            $formationId = $this->detectFormationId($lineupSlots);
            if ($formationId !== null) {
                $formationPositions = $this->teams->findFormationPositions($formationId);
            }
        }

        $errors = PreparationChecker::check($match, $selections, $lineupSlots, $formationPositions);
        if (!empty($errors)) {
            throw new ValidationException(['preparation' => implode(' ', $errors)]);
        }

        Database::beginTransaction();
        try {
            // Mark lineup starters
            foreach ($lineupSlots as $slot) {
                if ($slot['formation_position_id'] !== null) {
                    $this->selections->update((int) $slot['match_selection_id'], ['is_starting' => 1]);
                }
            }

            // Mark bench players
            $starterSelectionIds = array_filter(
                array_column($lineupSlots, 'match_selection_id'),
                fn($id, $i) => $lineupSlots[$i]['formation_position_id'] !== null,
                ARRAY_FILTER_USE_BOTH
            );

            foreach ($selections as $s) {
                if ($s['attendance_status'] === AttendanceStatus::Present->value
                    && !in_array((int) $s['id'], array_map('intval', $starterSelectionIds), true)) {
                    $this->selections->update((int) $s['id'], ['is_on_bench' => 1]);
                }
            }

            $this->matches->updateStatus((int) $match['id'], MatchStatus::Prepared->value);

            $this->audit->log(
                userId:     (int) $user['id'],
                entityType: 'match',
                entityId:   (int) $match['id'],
                actionKey:  'match.preparation_confirmed',
                matchId:    (int) $match['id'],
            );

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }
    }

    private function requirePreparable(array $match): void
    {
        if (!in_array($match['status'], [
            MatchStatus::Planned->value,
            MatchStatus::Prepared->value,
        ], true)) {
            throw new DomainException('Match preparation is only available for planned or prepared matches.');
        }
    }

    private function detectFormationId(array $lineupSlots): ?int
    {
        // Formation ID is stored on formation_position rows, JOINed in LineupRepository::findByMatch.
        // The lineup slot row includes formation_id when the repository SELECTs fp.formation_id.
        foreach ($lineupSlots as $slot) {
            if (isset($slot['formation_id'])) {
                return (int) $slot['formation_id'];
            }
        }
        return null;
    }
}

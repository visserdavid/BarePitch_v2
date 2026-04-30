<?php

declare(strict_types=1);

namespace BarePitch\Services;

use BarePitch\Core\Database;
use BarePitch\Core\Exceptions\DomainException;
use BarePitch\Domain\MatchStatus;
use BarePitch\Repositories\MatchRepository;

class MatchService
{
    public function __construct(
        private readonly MatchRepository $matches,
        private readonly AuditService    $audit,
    ) {}

    /**
     * Creates a new match in 'planned' status.
     * Validates that phase belongs to the team's season — validated by controller before calling.
     */
    public function create(array $user, array $team, array $data): int
    {
        Database::beginTransaction();
        try {
            $matchId = $this->matches->create([
                'team_id'                              => $team['id'],
                'phase_id'                             => $data['phase_id'],
                'date'                                 => $data['date'],
                'kick_off_time'                        => $data['kick_off_time'] ?? null,
                'opponent_name'                        => $data['opponent_name'],
                'home_away'                            => $data['home_away'] ?? 'home',
                'match_type'                           => $data['match_type'] ?? 'league',
                'regular_half_duration_minutes'        => $data['regular_half_duration_minutes'] ?? 45,
                'extra_time_half_duration_minutes'     => $data['extra_time_half_duration_minutes'] ?? 15,
                'status'                               => MatchStatus::Planned->value,
                'created_by'                           => $user['id'],
            ]);

            $this->audit->log(
                userId:     (int) $user['id'],
                entityType: 'match',
                entityId:   $matchId,
                actionKey:  'match.created',
                matchId:    $matchId,
            );

            Database::commit();
            return $matchId;
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Updates match metadata. Only allowed for planned/prepared matches.
     */
    public function update(array $user, array $match, array $data): void
    {
        if (!in_array($match['status'], [
            MatchStatus::Planned->value,
            MatchStatus::Prepared->value,
        ], true)) {
            throw new DomainException('Match details can only be edited before the match starts.');
        }

        Database::beginTransaction();
        try {
            $this->matches->update((int) $match['id'], $data);
            $this->audit->log(
                userId:     (int) $user['id'],
                entityType: 'match',
                entityId:   (int) $match['id'],
                actionKey:  'match.updated',
                matchId:    (int) $match['id'],
            );
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Soft-deletes a match. Only allowed for planned matches with no events.
     */
    public function delete(array $user, array $match): void
    {
        if ($match['status'] !== MatchStatus::Planned->value) {
            throw new DomainException('Only planned matches without events can be deleted.');
        }

        Database::beginTransaction();
        try {
            $this->matches->softDelete((int) $match['id']);
            $this->audit->log(
                userId:     (int) $user['id'],
                entityType: 'match',
                entityId:   (int) $match['id'],
                actionKey:  'match.deleted',
                matchId:    (int) $match['id'],
            );
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }
    }
}

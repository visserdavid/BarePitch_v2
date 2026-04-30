<?php

declare(strict_types=1);

namespace BarePitch\Services;

use BarePitch\Core\Database;
use BarePitch\Repositories\PlayerRepository;

class PlayerService
{
    public function __construct(
        private readonly PlayerRepository $players,
        private readonly AuditService     $audit,
    ) {}

    /**
     * Creates a new player and optionally attaches a season context.
     *
     * @param array $user   Authenticated user row
     * @param array $team   Current team context row (must include 'id' and optionally 'current_season_id')
     * @param array $data   Player data: first_name, last_name, display_name (optional),
     *                      squad_number (optional), preferred_line (optional),
     *                      preferred_foot (optional)
     * @return int  The new player ID
     */
    public function create(array $user, array $team, array $data): int
    {
        $seasonId = (int) ($team['current_season_id'] ?? 0);

        Database::beginTransaction();
        try {
            $playerId = $this->players->create([
                'first_name'   => $data['first_name'],
                'last_name'    => $data['last_name'],
                'display_name' => $data['display_name'] ?? null,
            ]);

            if ($seasonId > 0) {
                $this->players->createSeasonContext([
                    'player_id'         => $playerId,
                    'season_id'         => $seasonId,
                    'team_id'           => (int) $team['id'],
                    'preferred_line'    => $data['preferred_line'] ?? null,
                    'preferred_foot'    => $data['preferred_foot'] ?? null,
                    'squad_number'      => $data['squad_number'] ?? null,
                    'is_guest_eligible' => 0,
                ]);
            }

            $this->audit->log(
                userId:     (int) $user['id'],
                entityType: 'player',
                entityId:   $playerId,
                actionKey:  'player.created',
            );

            Database::commit();
            return $playerId;
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }
    }
}

<?php

declare(strict_types=1);

namespace BarePitch\Repositories;

class PhaseRepository
{
    public function __construct(private readonly \PDO $pdo) {}

    /**
     * Returns all phases for the active season(s) belonging to the given team.
     */
    public function findByTeam(int $teamId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT p.* FROM phase p
                 INNER JOIN season s ON s.id = p.season_id
                 WHERE s.team_id = ? AND s.is_active = 1
                 ORDER BY p.sort_order, p.id'
            );
            $stmt->execute([$teamId]);
            return $stmt->fetchAll();
        } catch (\Throwable) {
            return [];
        }
    }
}

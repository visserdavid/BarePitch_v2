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
        $stmt = $this->pdo->prepare(
            'SELECT p.* FROM phase p
             INNER JOIN season s ON s.id = p.season_id
             INNER JOIN team t ON t.season_id = s.id
             WHERE t.id = ? AND s.is_active = 1
             ORDER BY p.number, p.id'
        );
        $stmt->execute([$teamId]);
        return $stmt->fetchAll() ?: [];
    }
}

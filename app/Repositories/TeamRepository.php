<?php

declare(strict_types=1);

namespace BarePitch\Repositories;

class TeamRepository
{
    public function __construct(private readonly \PDO $pdo) {}

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM team WHERE id = ? AND is_active = 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** Admins see all teams; others see only teams they have a role in */
    public function findAccessibleForUser(int $userId, bool $isAdmin): array
    {
        if ($isAdmin) {
            return $this->pdo->query('SELECT * FROM team WHERE is_active = 1 ORDER BY name')->fetchAll();
        }
        $stmt = $this->pdo->prepare(
            'SELECT t.* FROM team t
             INNER JOIN user_team_role utr ON utr.team_id = t.id
             WHERE utr.user_id = ? AND t.is_active = 1
             ORDER BY t.name'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /** Returns active formations for a team */
    public function findFormations(int $teamId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM formation WHERE team_id = ? AND is_active = 1 ORDER BY name'
        );
        $stmt->execute([$teamId]);
        return $stmt->fetchAll();
    }

    /** Returns formation_position rows for a given formation */
    public function findFormationPositions(int $formationId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM formation_position WHERE formation_id = ? ORDER BY sort_order, id'
        );
        $stmt->execute([$formationId]);
        return $stmt->fetchAll();
    }
}

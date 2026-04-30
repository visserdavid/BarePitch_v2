<?php

declare(strict_types=1);

namespace BarePitch\Repositories;

class UserRepository
{
    public function __construct(private readonly \PDO $pdo) {}

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM user WHERE id = ? AND is_active = 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM user WHERE email = ? AND is_active = 1');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public function findActive(): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM user WHERE is_active = 1 ORDER BY last_name, first_name');
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Returns all user_team_role rows for this user */
    public function getTeamRoles(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM user_team_role WHERE user_id = ?');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function hasRoleForTeam(int $userId, int $teamId, string $roleKey): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM user_team_role WHERE user_id = ? AND team_id = ? AND role_key = ? LIMIT 1'
        );
        $stmt->execute([$userId, $teamId, $roleKey]);
        return (bool) $stmt->fetchColumn();
    }

    public function isAdministrator(int $userId): bool
    {
        $stmt = $this->pdo->prepare('SELECT is_administrator FROM user WHERE id = ? AND is_active = 1');
        $stmt->execute([$userId]);
        return (bool) $stmt->fetchColumn();
    }

    /** Returns all user_team_role rows for a given team */
    public function findRolesForTeam(int $teamId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM user_team_role WHERE team_id = ?');
        $stmt->execute([$teamId]);
        return $stmt->fetchAll();
    }
}

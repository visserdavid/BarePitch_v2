<?php

declare(strict_types=1);

namespace BarePitch\Repositories;

class PlayerRepository
{
    public function __construct(private readonly \PDO $pdo) {}

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM player WHERE id = ? AND is_active = 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** Returns players with their season context for a given team+season */
    public function findActiveByTeamAndSeason(int $teamId, int $seasonId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, psc.id AS context_id, psc.preferred_line, psc.preferred_foot,
                    psc.squad_number, psc.is_guest_eligible
             FROM player p
             INNER JOIN player_season_context psc
                 ON psc.player_id = p.id AND psc.season_id = ? AND psc.team_id = ?
             WHERE p.is_active = 1
             ORDER BY psc.squad_number, p.last_name, p.first_name'
        );
        $stmt->execute([$seasonId, $teamId]);
        return $stmt->fetchAll();
    }

    public function findSeasonContext(int $playerId, int $seasonId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM player_season_context WHERE player_id = ? AND season_id = ?'
        );
        $stmt->execute([$playerId, $seasonId]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO player (first_name, last_name, display_name) VALUES (?, ?, ?)'
        );
        $stmt->execute([
            $data['first_name'],
            $data['last_name'],
            $data['display_name'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function createSeasonContext(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO player_season_context
             (player_id, season_id, team_id, preferred_line, preferred_foot, squad_number, is_guest_eligible)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['player_id'],
            $data['season_id'],
            $data['team_id'],
            $data['preferred_line'] ?? null,
            $data['preferred_foot'] ?? null,
            $data['squad_number'] ?? null,
            $data['is_guest_eligible'] ?? 0,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /** Bulk load players by array of IDs */
    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT * FROM player WHERE id IN ($placeholders) AND is_active = 1"
        );
        $stmt->execute(array_map('intval', array_values($ids)));
        return $stmt->fetchAll();
    }
}

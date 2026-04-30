<?php

declare(strict_types=1);

namespace BarePitch\Repositories;

class SelectionRepository
{
    public function __construct(private readonly \PDO $pdo) {}

    public function findByMatch(int $matchId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ms.*, p.display_name, p.first_name, p.last_name
             FROM match_selection ms
             INNER JOIN player p ON p.id = ms.player_id
             WHERE ms.match_id = ?
             ORDER BY p.last_name, p.first_name'
        );
        $stmt->execute([$matchId]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM match_selection WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findByMatchAndPlayer(int $matchId, int $playerId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM match_selection WHERE match_id = ? AND player_id = ?'
        );
        $stmt->execute([$matchId, $playerId]);
        return $stmt->fetch() ?: null;
    }

    public function findPresentByMatch(int $matchId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ms.*, p.display_name, p.first_name, p.last_name
             FROM match_selection ms
             INNER JOIN player p ON p.id = ms.player_id
             WHERE ms.match_id = ? AND ms.attendance_status = ?
             ORDER BY p.last_name, p.first_name'
        );
        $stmt->execute([$matchId, 'present']);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO match_selection
             (match_id, player_id, player_season_context_id, attendance_status)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['match_id'],
            $data['player_id'],
            $data['player_season_context_id'] ?? null,
            $data['attendance_status'] ?? 'present',
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $allowed = ['attendance_status', 'absence_reason', 'injury_note',
                    'is_starting', 'is_on_bench', 'is_active_on_field', 'is_sent_off',
                    'playing_time_seconds', 'shirt_number_override'];
        $sets = [];
        $params = [];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[] = "$col = ?";
                $params[] = $data[$col];
            }
        }
        if (empty($sets)) {
            return;
        }
        $params[] = $id;
        $stmt = $this->pdo->prepare(
            'UPDATE match_selection SET ' . implode(', ', $sets) . ' WHERE id = ?'
        );
        $stmt->execute($params);
    }

    /**
     * Upserts attendance for multiple players at once.
     * $playerAttendance: array of ['player_id' => int, 'status' => string, 'context_id' => int|null]
     */
    public function upsertAttendance(int $matchId, array $playerAttendance): void
    {
        foreach ($playerAttendance as $item) {
            $existing = $this->findByMatchAndPlayer($matchId, (int) $item['player_id']);
            if ($existing) {
                $this->update($existing['id'], ['attendance_status' => $item['status']]);
            } else {
                $this->create([
                    'match_id'                  => $matchId,
                    'player_id'                 => $item['player_id'],
                    'player_season_context_id'  => $item['context_id'] ?? null,
                    'attendance_status'         => $item['status'],
                ]);
            }
        }
    }

    public function findStartersForMatch(int $matchId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ms.*, p.display_name FROM match_selection ms
             INNER JOIN player p ON p.id = ms.player_id
             WHERE ms.match_id = ? AND ms.is_starting = 1'
        );
        $stmt->execute([$matchId]);
        return $stmt->fetchAll();
    }

    public function findBenchForMatch(int $matchId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ms.*, p.display_name FROM match_selection ms
             INNER JOIN player p ON p.id = ms.player_id
             WHERE ms.match_id = ? AND ms.is_on_bench = 1 AND ms.is_starting = 0'
        );
        $stmt->execute([$matchId]);
        return $stmt->fetchAll();
    }
}

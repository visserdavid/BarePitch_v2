<?php

declare(strict_types=1);

namespace BarePitch\Repositories;

class LineupRepository
{
    public function __construct(private readonly \PDO $pdo) {}

    public function findByMatch(int $matchId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT mls.*, fp.label AS position_label, fp.grid_row AS fp_row, fp.grid_col AS fp_col,
                    p.display_name AS player_name
             FROM match_lineup_slot mls
             LEFT JOIN formation_position fp ON fp.id = mls.formation_position_id
             LEFT JOIN match_selection ms ON ms.id = mls.match_selection_id
             LEFT JOIN player p ON p.id = ms.player_id
             WHERE mls.match_id = ? AND mls.is_active_slot = 1
             ORDER BY fp.sort_order, mls.id'
        );
        $stmt->execute([$matchId]);
        return $stmt->fetchAll();
    }

    public function findSlotByMatchAndSelection(int $matchId, int $selectionId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM match_lineup_slot WHERE match_id = ? AND match_selection_id = ?'
        );
        $stmt->execute([$matchId, $selectionId]);
        return $stmt->fetch() ?: null;
    }

    public function findSlotByGrid(int $matchId, int $row, int $col): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM match_lineup_slot WHERE match_id = ? AND grid_row = ? AND grid_col = ?'
        );
        $stmt->execute([$matchId, $row, $col]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO match_lineup_slot
             (match_id, match_selection_id, formation_position_id, grid_row, grid_col, is_active_slot)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['match_id'],
            $data['match_selection_id'],
            $data['formation_position_id'] ?? null,
            $data['grid_row'] ?? null,
            $data['grid_col'] ?? null,
            $data['is_active_slot'] ?? 1,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $allowed = ['formation_position_id', 'grid_row', 'grid_col', 'is_active_slot'];
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
            'UPDATE match_lineup_slot SET ' . implode(', ', $sets) . ' WHERE id = ?'
        );
        $stmt->execute($params);
    }

    public function deleteByMatch(int $matchId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM match_lineup_slot WHERE match_id = ?');
        $stmt->execute([$matchId]);
    }

    /**
     * Replaces all lineup slots for a match atomically.
     * Must be called inside a transaction (caller's responsibility).
     * $slots: array of arrays each with keys: match_selection_id, formation_position_id, grid_row, grid_col
     */
    public function replaceForMatch(int $matchId, array $slots): void
    {
        $this->deleteByMatch($matchId);
        foreach ($slots as $slot) {
            $this->create([
                'match_id'              => $matchId,
                'match_selection_id'    => $slot['match_selection_id'],
                'formation_position_id' => $slot['formation_position_id'] ?? null,
                'grid_row'              => $slot['grid_row'] ?? null,
                'grid_col'              => $slot['grid_col'] ?? null,
            ]);
        }
    }
}

<?php

declare(strict_types=1);

namespace BarePitch\Repositories;

class MatchRepository
{
    public function __construct(private readonly \PDO $pdo) {}

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM `match` WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** Scoped lookup — returns null if match doesn't belong to the given team */
    public function findByIdForTeam(int $id, int $teamId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM `match` WHERE id = ? AND team_id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$id, $teamId]);
        return $stmt->fetch() ?: null;
    }

    public function findByTeam(int $teamId, bool $includeDeleted = false): array
    {
        $sql = 'SELECT * FROM `match` WHERE team_id = ?';
        if (!$includeDeleted) {
            $sql .= ' AND deleted_at IS NULL';
        }
        $sql .= ' ORDER BY date DESC, kick_off_time DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$teamId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO `match`
             (team_id, phase_id, date, kick_off_time, opponent_name, home_away, match_type,
              regular_half_duration_minutes, extra_time_half_duration_minutes, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['team_id'],
            $data['phase_id'],
            $data['date'],
            $data['kick_off_time'] ?? null,
            $data['opponent_name'],
            $data['home_away'] ?? 'home',
            $data['match_type'] ?? 'league',
            $data['regular_half_duration_minutes'] ?? 45,
            $data['extra_time_half_duration_minutes'] ?? 15,
            $data['status'] ?? 'planned',
            $data['created_by'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateStatus(int $id, string $status, ?string $activePhase = null): void
    {
        if ($activePhase !== null) {
            $stmt = $this->pdo->prepare(
                'UPDATE `match` SET status = ?, active_phase = ? WHERE id = ?'
            );
            $stmt->execute([$status, $activePhase, $id]);
        } else {
            $stmt = $this->pdo->prepare('UPDATE `match` SET status = ? WHERE id = ?');
            $stmt->execute([$status, $id]);
        }
    }

    public function updateScore(int $id, int $goalsScored, int $goalsConceded): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE `match` SET goals_scored = ?, goals_conceded = ? WHERE id = ?'
        );
        $stmt->execute([$goalsScored, $goalsConceded, $id]);
    }

    public function update(int $id, array $data): void
    {
        // Note: 'status' is intentionally excluded — use updateStatus() for state transitions.
        $allowed = ['date', 'kick_off_time', 'opponent_name', 'home_away', 'match_type',
                    'regular_half_duration_minutes', 'notes', 'active_phase', 'finished_at'];
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
        $stmt = $this->pdo->prepare('UPDATE `match` SET ' . implode(', ', $sets) . ' WHERE id = ?');
        $stmt->execute($params);
    }

    public function findPeriods(int $matchId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM match_period WHERE match_id = ? ORDER BY sort_order'
        );
        $stmt->execute([$matchId]);
        return $stmt->fetchAll();
    }

    public function findPeriodByKey(int $matchId, string $periodKey): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM match_period WHERE match_id = ? AND period_key = ?'
        );
        $stmt->execute([$matchId, $periodKey]);
        return $stmt->fetch() ?: null;
    }

    public function createPeriod(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO match_period (match_id, period_key, sort_order, started_at, configured_duration_minutes)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['match_id'],
            $data['period_key'],
            $data['sort_order'],
            $data['started_at'] ?? null,
            $data['configured_duration_minutes'] ?? 45,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updatePeriod(int $periodId, array $data): void
    {
        $allowed = ['started_at', 'ended_at'];
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
        $params[] = $periodId;
        $stmt = $this->pdo->prepare(
            'UPDATE match_period SET ' . implode(', ', $sets) . ' WHERE id = ?'
        );
        $stmt->execute($params);
    }

    public function softDelete(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE `match` SET deleted_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$id]);
    }
}

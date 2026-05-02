<?php

declare(strict_types=1);

namespace BarePitch\Repositories;

class LockRepository
{
    public function __construct(private readonly \PDO $pdo) {}

    public function findActiveByMatch(int $matchId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM match_lock WHERE match_id = ? AND expires_at > NOW()'
        );
        $stmt->execute([$matchId]);
        return $stmt->fetch() ?: null;
    }

    public function findByMatch(int $matchId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM match_lock WHERE match_id = ?');
        $stmt->execute([$matchId]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO match_lock (match_id, user_id, locked_at, expires_at)
             VALUES (?, ?, NOW(), ?)'
        );
        $stmt->execute([
            $data['match_id'],
            $data['user_id'],
            $data['expires_at'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $allowed = ['user_id', 'locked_at', 'expires_at'];
        $sets = [];
        $params = [];

        foreach ($allowed as $column) {
            if (array_key_exists($column, $data)) {
                $sets[] = "{$column} = ?";
                $params[] = $data[$column];
            }
        }

        if ($sets === []) {
            return;
        }

        $params[] = $id;
        $stmt = $this->pdo->prepare('UPDATE match_lock SET ' . implode(', ', $sets) . ' WHERE id = ?');
        $stmt->execute($params);
    }

    public function release(int $matchId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM match_lock WHERE match_id = ?');
        $stmt->execute([$matchId]);
    }

    /**
     * Acquires or refreshes a lock for a match.
     * - If no active lock: create new lock
     * - If active lock by same user: extend it
     * - If active lock by different user: return that lock without modifying
     * Returns the lock row (existing or newly created).
     */
    public function acquireOrRefresh(int $matchId, int $userId, int $ttlSeconds = 120): array
    {
        $existing = $this->findActiveByMatch($matchId);
        if ($existing !== null) {
            if ((int) $existing['user_id'] === $userId) {
                $newExpiry = date('Y-m-d H:i:s', time() + $ttlSeconds);
                $this->update($existing['id'], ['expires_at' => $newExpiry]);
                $existing['expires_at'] = $newExpiry;
            }
            return $existing;
        }
        $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);
        $expired = $this->findByMatch($matchId);
        if ($expired !== null) {
            $this->update((int) $expired['id'], [
                'user_id' => $userId,
                'locked_at' => date('Y-m-d H:i:s'),
                'expires_at' => $expiresAt,
            ]);

            $stmt = $this->pdo->prepare('SELECT * FROM match_lock WHERE id = ?');
            $stmt->execute([$expired['id']]);
            return $stmt->fetch();
        }

        $id = $this->create([
            'match_id'   => $matchId,
            'user_id'    => $userId,
            'expires_at' => $expiresAt,
        ]);
        // Fetch the full row rather than constructing a partial array
        $stmt = $this->pdo->prepare('SELECT * FROM match_lock WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
}

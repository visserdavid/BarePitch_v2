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
        $stmt = $this->pdo->prepare(
            'UPDATE match_lock SET expires_at = ? WHERE id = ?'
        );
        $stmt->execute([$data['expires_at'], $id]);
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
        $id = $this->create([
            'match_id'   => $matchId,
            'user_id'    => $userId,
            'expires_at' => $expiresAt,
        ]);
        return ['id' => $id, 'match_id' => $matchId, 'user_id' => $userId, 'expires_at' => $expiresAt];
    }
}

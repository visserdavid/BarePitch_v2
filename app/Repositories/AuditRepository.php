<?php

declare(strict_types=1);

namespace BarePitch\Repositories;

class AuditRepository
{
    public function __construct(private readonly \PDO $pdo) {}

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO audit_log
             (entity_type, entity_id, match_id, user_id, action_key, field_name, old_value_json, new_value_json)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['entity_type'],
            $data['entity_id'],
            $data['match_id'] ?? null,
            $data['user_id'],
            $data['action_key'],
            $data['field_name'] ?? null,
            isset($data['old_value']) ? json_encode($data['old_value']) : null,
            isset($data['new_value']) ? json_encode($data['new_value']) : null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function findByMatch(int $matchId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM audit_log WHERE match_id = ? ORDER BY created_at ASC, id ASC'
        );
        $stmt->execute([$matchId]);
        return $stmt->fetchAll();
    }
}

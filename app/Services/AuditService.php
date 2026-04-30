<?php

declare(strict_types=1);

namespace BarePitch\Services;

use BarePitch\Repositories\AuditRepository;

class AuditService
{
    public function __construct(private readonly AuditRepository $audit) {}

    public function log(
        int $userId,
        string $entityType,
        int $entityId,
        string $actionKey,
        ?int $matchId = null,
        ?string $fieldName = null,
        mixed $oldValue = null,
        mixed $newValue = null
    ): void {
        $this->audit->create([
            'entity_type'    => $entityType,
            'entity_id'      => $entityId,
            'match_id'       => $matchId,
            'user_id'        => $userId,
            'action_key'     => $actionKey,
            'field_name'     => $fieldName,
            'old_value'      => $oldValue,
            'new_value'      => $newValue,
        ]);
    }
}

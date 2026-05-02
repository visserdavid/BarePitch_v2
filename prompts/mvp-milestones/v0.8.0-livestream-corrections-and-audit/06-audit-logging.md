# Audit Logging — v0.8.0

# Purpose
Implement `AuditService` and the `audit_log` table migration. Every finished-match correction must write an audit entry inside the same transaction. The audit log is append-only — no updates or deletes in normal operation.

# Required Context
See `01-shared-context.md`. This service is a dependency of `07-correct-finished-match-end-to-end.md`. Build this before implementing correction routes.

# Required Documentation
- `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md` — `audit_log` table schema
- `docs/BarePitch-v2-05-critical-behavior-specifications-v1.0.md` — section 16 (Audit Logging Behavior)
- `docs/BarePitch-v2-02-authorization-matrix-v1.0.md` — audit log access rows

# Scope

## Migration

Verify `docs/-01` for the `audit_log` table. If it is not yet in a migration file, create `database/migrations/NNNN_create_audit_log_table.sql`:

```sql
CREATE TABLE IF NOT EXISTS `audit_log` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`         INT UNSIGNED    NOT NULL,
    `match_id`        INT UNSIGNED    NOT NULL,
    `entity_type`     VARCHAR(64)     NOT NULL,
    `entity_id`       INT UNSIGNED    NOT NULL,
    `action_key`      VARCHAR(64)     NOT NULL,
    `field_name`      VARCHAR(128)        NULL,
    `old_value_json`  TEXT                NULL,
    `new_value_json`  TEXT                NULL,
    `created_at`      DATETIME        NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_audit_match`  (`match_id`),
    INDEX `idx_audit_user`   (`user_id`),
    INDEX `idx_audit_entity` (`entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

If `audit_log` already exists in migrations, verify the column set matches the above. If the existing schema differs, do not silently change it — flag the discrepancy for review.

## `AuditRepository`

Create `app/Repositories/AuditRepository.php`:

```php
<?php

namespace App\Repositories;

class AuditRepository
{
    public function __construct(private \PDO $db) {}

    public function insert(array $data): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO audit_log
                (user_id, match_id, entity_type, entity_id, action_key, field_name, old_value_json, new_value_json, created_at)
            VALUES
                (:user_id, :match_id, :entity_type, :entity_id, :action_key, :field_name, :old_value_json, :new_value_json, :created_at)
        ");
        $stmt->execute([
            'user_id'        => $data['user_id'],
            'match_id'       => $data['match_id'],
            'entity_type'    => $data['entity_type'],
            'entity_id'      => $data['entity_id'],
            'action_key'     => $data['action_key'],
            'field_name'     => $data['field_name'] ?? null,
            'old_value_json' => $data['old_value_json'] ?? null,
            'new_value_json' => $data['new_value_json'] ?? null,
            'created_at'     => $data['created_at'],
        ]);
    }

    public function findByMatch(int $matchId): array
    {
        $stmt = $this->db->prepare("
            SELECT al.*, u.email AS user_email
            FROM audit_log al
            JOIN user u ON al.user_id = u.id
            WHERE al.match_id = :match_id
            ORDER BY al.created_at DESC, al.id DESC
        ");
        $stmt->execute(['match_id' => $matchId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
```

## `AuditService`

Create `app/Services/AuditService.php`:

```php
<?php

namespace App\Services;

class AuditService
{
    public function __construct(
        private \App\Repositories\AuditRepository $auditRepository
    ) {}

    /**
     * Write one audit log entry.
     *
     * Must be called inside an open transaction (owned by the caller).
     * If this insert fails, the caller's transaction rolls back and no correction is committed.
     *
     * @param int         $userId      The user performing the correction
     * @param int         $matchId     The match being corrected
     * @param string      $entityType  e.g. 'match_event', 'substitution', 'penalty_shootout_attempt'
     * @param int         $entityId    ID of the corrected row
     * @param string      $actionKey   e.g. 'correction'
     * @param string|null $fieldName   Which field was changed
     * @param mixed       $oldValue    Previous value (will be JSON-encoded)
     * @param mixed       $newValue    New value (will be JSON-encoded)
     */
    public function log(
        int    $userId,
        int    $matchId,
        string $entityType,
        int    $entityId,
        string $actionKey,
        ?string $fieldName = null,
        mixed  $oldValue   = null,
        mixed  $newValue   = null
    ): void {
        $this->auditRepository->insert([
            'user_id'        => $userId,
            'match_id'       => $matchId,
            'entity_type'    => $entityType,
            'entity_id'      => $entityId,
            'action_key'     => $actionKey,
            'field_name'     => $fieldName,
            'old_value_json' => $oldValue !== null  ? json_encode($oldValue,  JSON_UNESCAPED_UNICODE) : null,
            'new_value_json' => $newValue !== null  ? json_encode($newValue,  JSON_UNESCAPED_UNICODE) : null,
            'created_at'     => date('Y-m-d H:i:s'),
        ]);
    }
}
```

## Audit Log Display

Coaches and administrators can view the audit log for a finished match at:

```
GET /matches/{match_id}/audit-log
```

**Controller**: `AuditLogController::show(int $matchId)`

**Access**: coach (same team) or administrator; CSRF not required (read-only GET)

**Behavior**:
1. Load match by ID
2. Check `CorrectionPolicy::canCorrect()` — 403 if denied
3. Load all audit entries for this match via `AuditRepository::findByMatch()`
4. Render `app/Views/corrections/audit-log.php`

**View** (`app/Views/corrections/audit-log.php`):
- Show match summary (opponent, date, score)
- Table: Date/Time | Editor | Entity | Field | Old Value | New Value
- Old and new values: parse `old_value_json` / `new_value_json` and display human-readable; for NULL values show "—"
- Paginate if > 100 entries (server-side, `?page=N` query param)
- Output-escape all values with `e()`

## Append-Only Constraint

The `audit_log` table must not have any UPDATE or DELETE paths in normal product operation. The only allowed DML is INSERT.

To enforce this at the application layer:
- `AuditRepository` exposes only `insert()` and read methods — no update or delete methods
- No migration may add a `deleted_at` column or soft-delete flag to `audit_log`
- Retention policy (if needed post-MVP) is a separate admin-only archival operation outside this scope

# Out of Scope
- Audit log for non-correction writes (e.g., goal registrations during live match) — only finished-match corrections are audited in v0.8.0
- Audit log export (post-MVP)
- Admin-only audit log review across all matches (post-MVP)
- Automated retention or archival (post-MVP)

# Architectural Rules
- `AuditService::log()` is always called inside the caller's transaction — never outside, never after commit
- A failure in `AuditService::log()` must roll back the entire correction transaction
- `AuditRepository` uses prepared statements with named parameters — no string interpolation
- JSON encoding of old/new values preserves Unicode; null values stored as SQL NULL, not the string `"null"`
- The `audit_log` table has no foreign key to `match_event` or `substitution` — `entity_id` is a bare integer to allow flexibility across entity types

# Acceptance Criteria
- `audit_log` table exists in migrations with the correct column set
- `AuditService::log()` inserts one row per changed field per correction
- A correction that changes 2 fields produces 2 audit rows, each with correct `field_name`, `old_value_json`, `new_value_json`
- AU-01: correction made by coach — audit entry written with correct user_id, match_id, entity details
- AU-02: trainer attempts correction — blocked at policy layer; zero audit entries written
- AU-03: audit entry written in same transaction as correction — if correction is rolled back, audit entry is also absent
- GET `/matches/{id}/audit-log` returns 200 for coach; 403 for trainer; shows all entries ordered by date desc
- Output-escaping prevents XSS in old/new value display

# Verification
- PHP syntax check all new files
- Insert a correction; verify `audit_log` has 1+ rows for that match with correct fields
- Roll back a correction by causing a validation error after `AuditService::log()` has been called; verify no audit row was committed
- Load audit log page as coach — verify rows render correctly with escaped values
- Load audit log page as trainer — verify 403

# Handoff Note
`07-correct-finished-match-end-to-end.md` implements the correction routes that call this service. `08-testing-and-verification.md` covers the full PHPUnit test suite for this milestone.

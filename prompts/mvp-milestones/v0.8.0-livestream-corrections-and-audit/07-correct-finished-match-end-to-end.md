# Correct Finished Match End to End

# Purpose
Implement the finished match correction interface: the GET correction screen, and POST routes for correcting match events, substitution records, and penalty shootout attempts. Every correction must: acquire-check the lock, validate the change, execute within a transaction, recalculate affected derived/cached data, and write an audit log entry. The match must remain `finished` after every correction.

# Required Context
See `01-shared-context.md`. Prompts 02 through 06 must be complete. `LockService`, `ScoreRecalculationService` (prompt 05), and `AuditService` (prompt 06) must exist before implementing correction routes.

# Required Documentation
- `docs/BarePitch-v2-02-authorization-matrix-v1.0.md` — `finished correction: view UI`, `event update`, `substitution update`, `shootout update` rows
- `docs/BarePitch-v2-05-critical-behavior-specifications-v1.0.md` — section 15 (Finished Match Correction Behavior)
- `docs/BarePitch-v2-08-route-api-specification-v1.0.md` — section 21 (Finished Match Correction Routes)
- `docs/BarePitch-v2-04-state-and-derived-data-policy-v1.0.md` — recalculation triggers
- `docs/BarePitch-v2-10-data-integrity-test-scenarios-v1.0.md` — SC-06, AU-01, AU-02, AU-03, MS-04, MS-05

# Scope

## Correction Authorization

Only coach and administrator may correct finished matches. Trainer and team_manager must receive 403.

Create `app/Policies/CorrectionPolicy.php`:

```php
<?php

namespace App\Policies;

class CorrectionPolicy
{
    /**
     * Returns true if the user may view the correction screen and submit corrections
     * for the given finished match.
     *
     * Allowed: administrator, coach (same team)
     * Denied: trainer, team_manager, public
     */
    public static function canCorrect(array $user, array $match, ?array $userTeamRole): bool
    {
        if ($user['is_administrator']) {
            return true;
        }
        if ($userTeamRole === null) {
            return false;
        }
        return $userTeamRole['role_key'] === 'coach'
            && (int) $userTeamRole['team_id'] === (int) $match['team_id'];
    }
}
```

## GET /matches/{match_id}/correct — Correction Screen

**Controller**: `CorrectionController::show(int $matchId)`

**Access**: authenticated — coach or administrator

**Behavior**:
1. Load match by ID
2. Verify team access
3. Verify match status is `finished` (return 404 or redirect otherwise)
4. Check `CorrectionPolicy::canCorrect()` — return 403 if denied
5. Attempt to acquire lock via `LockService::acquireOrRefresh($matchId, $userId)`
   - On `LockConflictException`: show correction screen in **read-only mode** with a message indicating another user is editing
6. Load events, substitutions, shootout attempts for the match
7. Render `app/Views/corrections/show.php`

**View**: `app/Views/corrections/show.php` must include:
- Match summary (score, opponent, date)
- Editable event list (scorer, assist, zone, outcome for each correctable event)
- Editable substitution list
- Editable shootout attempt list (if any)
- CSRF token in all forms
- Lock refresh JavaScript (from prompt 04)
- Read-only mode indicator if lock is held by another user (no edit forms shown in read-only mode)

## POST /matches/{match_id}/events/{event_id}/update — Correct Event

**Controller**: `CorrectionController::updateEvent(int $matchId, int $eventId)`

**Access**: authenticated — coach or administrator; CSRF required

**Validator**: `app/Http/Requests/CorrectionEventRequest.php`

Validatable fields (all optional; only changed fields need to be submitted):
- `player_selection_id` — integer or null; must belong to the match selection if provided
- `assist_selection_id` — integer or null; must belong to the match selection if provided and not same as scorer
- `zone_code` — must be one of `tl`, `tm`, `tr`, `ml`, `mm`, `mr`, `bl`, `bm`, `br`, or null
- `outcome` — must be one of `scored`, `missed`, `none` if provided
- `note_text` — max 500 characters; normalized to null if empty

**Service method**: `CorrectionService::correctEvent(int $matchId, int $eventId, int $userId, array $changes): void`

Full implementation:

```php
public function correctEvent(int $matchId, int $eventId, int $userId, array $changes): void
{
    $this->db->beginTransaction();
    try {
        // 1. Check match is finished
        $match = $this->matchRepository->findById($matchId);
        if ($match['status'] !== 'finished') {
            throw new \App\Domain\Exceptions\InvalidStateException('Match is not finished.');
        }

        // 2. Assert lock ownership (inside transaction to prevent TOCTOU)
        $this->lockService->assertOwnsActiveLock($matchId, $userId);

        // 3. Load the event, verify it belongs to this match
        $event = $this->matchEventRepository->findByIdAndMatch($eventId, $matchId);
        if ($event === null) {
            throw new \App\Domain\Exceptions\NotFoundException('Event not found.');
        }

        // 4. Build old and new value snapshots for audit log
        $correctedFields = [];
        foreach ($changes as $field => $newValue) {
            $oldValue = $event[$field] ?? null;
            if ($oldValue !== $newValue) {
                $correctedFields[$field] = ['old' => $oldValue, 'new' => $newValue];
            }
        }

        if (empty($correctedFields)) {
            $this->db->rollBack();
            return; // No actual change — nothing to do
        }

        // 5. Update the event source record
        $this->matchEventRepository->updateFields($eventId, $changes);

        // 6. Recalculate score if the change affects a scored event
        $scoreAffectingTypes = ['goal', 'penalty'];
        if (in_array($event['event_type'], $scoreAffectingTypes, true)
            || isset($changes['outcome'])
            || isset($changes['team_side'])
        ) {
            $this->scoreRecalculationService->recalculateMatchScore($matchId);
        }

        // 7. Write audit log entry for each changed field
        foreach ($correctedFields as $field => $values) {
            $this->auditService->log(
                userId: $userId,
                matchId: $matchId,
                entityType: 'match_event',
                entityId: $eventId,
                actionKey: 'correction',
                fieldName: $field,
                oldValue: $values['old'],
                newValue: $values['new']
            );
        }

        // 8. Match status remains finished — do not change it
        // match.status is never touched in correction transactions

        $this->db->commit();
    } catch (\Throwable $e) {
        $this->db->rollBack();
        throw $e;
    }
}
```

**Correctable fields on `match_event`**:

| Field | Notes |
|---|---|
| `player_selection_id` | Scorer; must be valid match selection ID or null for opponent events |
| `assist_selection_id` | Optional; only for goals, not penalties; cannot equal scorer |
| `zone_code` | One of the 9 zone codes or null |
| `outcome` | `scored`, `missed`, `none` — only for penalty events |
| `note_text` | Max 500 characters; null if empty |

Fields that may not be corrected: `event_type`, `match_id`, `period_id`, `match_second`, `created_by_user_id`, `created_at`.

**Controller behavior**:
1. Validate CSRF
2. Load match, verify access
3. Check `CorrectionPolicy::canCorrect()`
4. Validate request via `CorrectionEventRequest`
5. Call `CorrectionService::correctEvent()`
6. On success: redirect to `/matches/{match_id}/correct` with success flash
7. On `LockConflictException`: redirect with `locked` error
8. On `NotFoundException` or `InvalidStateException`: redirect with error message
9. On validation failure: redirect back with validation errors

## POST /matches/{match_id}/substitutions/{substitution_id}/update — Correct Substitution

**Controller**: `CorrectionController::updateSubstitution(int $matchId, int $substitutionId)`

**Access**: authenticated — coach or administrator; CSRF required

**Validator**: `app/Http/Requests/CorrectionSubstitutionRequest.php`

Validatable fields:
- `player_off_selection_id` — must belong to match selection
- `player_on_selection_id` — must belong to match selection; must differ from player_off
- `match_second` — positive integer

**Service method**: `CorrectionService::correctSubstitution(int $matchId, int $substitutionId, int $userId, array $changes): void`

```php
public function correctSubstitution(int $matchId, int $substitutionId, int $userId, array $changes): void
{
    $this->db->beginTransaction();
    try {
        $match = $this->matchRepository->findById($matchId);
        if ($match['status'] !== 'finished') {
            throw new \App\Domain\Exceptions\InvalidStateException('Match is not finished.');
        }

        $this->lockService->assertOwnsActiveLock($matchId, $userId);

        $substitution = $this->substitutionRepository->findByIdAndMatch($substitutionId, $matchId);
        if ($substitution === null) {
            throw new \App\Domain\Exceptions\NotFoundException('Substitution not found.');
        }

        $correctedFields = [];
        foreach ($changes as $field => $newValue) {
            $oldValue = $substitution[$field] ?? null;
            if ($oldValue !== $newValue) {
                $correctedFields[$field] = ['old' => $oldValue, 'new' => $newValue];
            }
        }

        if (empty($correctedFields)) {
            $this->db->rollBack();
            return;
        }

        // Update substitution source record
        $this->substitutionRepository->updateFields($substitutionId, $changes);

        // Audit log for each changed field
        foreach ($correctedFields as $field => $values) {
            $this->auditService->log(
                userId: $userId,
                matchId: $matchId,
                entityType: 'substitution',
                entityId: $substitutionId,
                actionKey: 'correction',
                fieldName: $field,
                oldValue: $values['old'],
                newValue: $values['new']
            );
        }

        // Score is not affected by substitution corrections.
        // Playing-time caches may be affected; recalculate if needed.
        // Note: Full playing-time recalculation is deferred to a future hardening pass (v0.9.0)
        // unless playing_time_seconds is actively displayed in this context.

        $this->db->commit();
    } catch (\Throwable $e) {
        $this->db->rollBack();
        throw $e;
    }
}
```

**Correctable fields on `substitution`**:

| Field | Notes |
|---|---|
| `player_off_selection_id` | Must remain valid match selection ID |
| `player_on_selection_id` | Must remain valid match selection ID; cannot equal player_off |
| `match_second` | Positive integer |

Fields that may not be corrected: `match_id`, `period_id`, `created_by_user_id`, `created_at`.

## POST /matches/{match_id}/shootout/attempts/{attempt_id}/update — Correct Shootout Attempt

**Controller**: `CorrectionController::updateShootoutAttempt(int $matchId, int $attemptId)`

**Access**: authenticated — coach or administrator; CSRF required

**Validator**: `app/Http/Requests/CorrectionShootoutRequest.php`

Validatable fields:
- `player_selection_id` — integer or null; must belong to match selection or null for opponent
- `player_name_text` — max 120 characters (per domain schema); used for opponent attempts
- `outcome` — `scored` or `missed`
- `zone_code` — one of the 9 zone codes or null

**Service method**: `CorrectionService::correctShootoutAttempt(int $matchId, int $attemptId, int $userId, array $changes): void`

```php
public function correctShootoutAttempt(int $matchId, int $attemptId, int $userId, array $changes): void
{
    $this->db->beginTransaction();
    try {
        $match = $this->matchRepository->findById($matchId);
        if ($match['status'] !== 'finished') {
            throw new \App\Domain\Exceptions\InvalidStateException('Match is not finished.');
        }

        $this->lockService->assertOwnsActiveLock($matchId, $userId);

        $attempt = $this->shootoutRepository->findByIdAndMatch($attemptId, $matchId);
        if ($attempt === null) {
            throw new \App\Domain\Exceptions\NotFoundException('Shootout attempt not found.');
        }

        $correctedFields = [];
        foreach ($changes as $field => $newValue) {
            $oldValue = $attempt[$field] ?? null;
            if ($oldValue !== $newValue) {
                $correctedFields[$field] = ['old' => $oldValue, 'new' => $newValue];
            }
        }

        if (empty($correctedFields)) {
            $this->db->rollBack();
            return;
        }

        // Update source record
        $this->shootoutRepository->updateFields($attemptId, $changes);

        // Recalculate shootout score from source
        $this->scoreRecalculationService->recalculateShootoutScore($matchId);

        // Audit log
        foreach ($correctedFields as $field => $values) {
            $this->auditService->log(
                userId: $userId,
                matchId: $matchId,
                entityType: 'penalty_shootout_attempt',
                entityId: $attemptId,
                actionKey: 'correction',
                fieldName: $field,
                oldValue: $values['old'],
                newValue: $values['new']
            );
        }

        $this->db->commit();
    } catch (\Throwable $e) {
        $this->db->rollBack();
        throw $e;
    }
}
```

**Correctable fields on `penalty_shootout_attempt`**:

| Field | Notes |
|---|---|
| `player_selection_id` | Own team taker selection; null for opponent |
| `player_name_text` | Max 120 characters; opponent taker name |
| `outcome` | `scored` or `missed` |
| `zone_code` | One of the 9 zone codes or null |

Fields that may not be corrected: `match_id`, `attempt_order`, `round_number`, `team_side`, `is_sudden_death`, `created_by_user_id`, `created_at`.

## Repository Methods Required

The following methods must be present or added to their respective repositories:

**`MatchEventRepository`**:
- `findByIdAndMatch(int $eventId, int $matchId): ?array`
- `updateFields(int $eventId, array $fields): void` — updates only the allowed correction fields via a prepared statement with a dynamic safe field list

**`SubstitutionRepository`**:
- `findByIdAndMatch(int $substitutionId, int $matchId): ?array`
- `updateFields(int $substitutionId, array $fields): void`

**`ShootoutRepository`**:
- `findByIdAndMatch(int $attemptId, int $matchId): ?array`
- `updateFields(int $attemptId, array $fields): void`

The `updateFields` methods must only allow known correction-safe field names. Implement a whitelist:

```php
private const ALLOWED_EVENT_FIELDS = [
    'player_selection_id', 'assist_selection_id', 'zone_code', 'outcome', 'note_text'
];

public function updateFields(int $eventId, array $fields): void
{
    $safe = array_intersect_key($fields, array_flip(self::ALLOWED_EVENT_FIELDS));
    if (empty($safe)) {
        return;
    }
    $setClauses = implode(', ', array_map(fn($k) => "`{$k}` = :{$k}", array_keys($safe)));
    $stmt = $this->db->prepare("UPDATE match_event SET {$setClauses} WHERE id = :id");
    $safe['id'] = $eventId;
    $stmt->execute($safe);
}
```

Apply the same whitelist pattern to `SubstitutionRepository::updateFields()` and `ShootoutRepository::updateFields()`.

## CorrectionService Constructor

```php
class CorrectionService
{
    public function __construct(
        private \PDO $db,
        private \App\Repositories\MatchRepository $matchRepository,
        private \App\Repositories\MatchEventRepository $matchEventRepository,
        private \App\Repositories\SubstitutionRepository $substitutionRepository,
        private \App\Repositories\ShootoutRepository $shootoutRepository,
        private \App\Services\LockService $lockService,
        private \App\Services\ScoreRecalculationService $scoreRecalculationService,
        private \App\Services\AuditService $auditService
    ) {}
}
```

## Match Status Invariant

`match.status` is read at the start of every correction method to confirm it is `finished`. It is never written to inside a correction transaction. If a future route accidentally passes a non-finished match through the correction screen, the service throws `InvalidStateException` before any mutation.

After every correction, the match status in the database remains `finished`. Verify this in tests.

# Out of Scope
- Corrections to `match_type`, `date`, `opponent_name` (metadata correction is not part of the correction screen)
- Creating or deleting events (corrections only update existing events)
- Playing-time full recalculation from substitution corrections (deferred to v0.9.0 hardening)
- Ratings correction (post-MVP)

# Architectural Rules
- Every correction method begins with a transaction that wraps: lock assertion → source record update → derived-data recalculation → audit log write → commit
- `LockService::assertOwnsActiveLock()` is always the first call inside the transaction — before any mutation
- `AuditService::log()` is always called inside the transaction, before commit — never after commit
- `ScoreRecalculationService` is called inside the transaction — score cache and source data change atomically
- `match.status` is never written in any correction path — it must remain `finished`
- Repository `updateFields()` methods use a field whitelist — no arbitrary column names can be injected
- Controller catches `LockConflictException`, `InvalidStateException`, `NotFoundException` and maps them to user-facing errors without exposing internal details

# Acceptance Criteria
- GET `/matches/{id}/correct` returns correction screen for finished match with coach role
- GET `/matches/{id}/correct` returns 403 for trainer and team_manager
- Correction screen shows read-only mode when lock is held by another user
- Event correction: POST with valid changed scorer → source record updated, score recalculated, audit log written, match remains `finished`
- Event correction: POST without owning lock → returns `locked` error, no data change
- Substitution correction: POST with valid changed player → source record updated, audit log written, match remains `finished`
- Shootout attempt correction: POST with outcome change → source record updated, shootout score recalculated, audit log written, match remains `finished`
- AU-02: trainer submits correction → blocked with 403, no audit entry, source data unchanged
- SC-06: score correction scenario — see test scenarios doc — correction changes team_side on a goal event → score recalculates from source events, cached score updates
- MS-04: correction is allowed on finished match
- MS-05: starting match after correction is blocked (match remains `finished`)

# Verification
- PHP syntax check all new/modified files
- Test correction screen: GET as coach on finished match → 200 with form; GET as trainer → 403
- Test event correction: submit valid change, verify `match_event` row updated, `audit_log` row created, `match.goals_scored`/`goals_conceded` recalculated, `match.status` still `finished`
- Test substitution correction: submit valid change, verify `substitution` row updated, `audit_log` row created
- Test shootout correction: submit outcome change, verify `penalty_shootout_attempt` row updated, `match.shootout_goals_scored` recalculated, `audit_log` row created
- Test lock guard: release lock, then submit correction → verify locked error returned, no source data change
- Test AU-02: submit correction as trainer → verify 403, no `audit_log` row inserted

# Handoff Note
`08-testing-and-verification.md` covers PHPUnit tests for all correction scenarios.

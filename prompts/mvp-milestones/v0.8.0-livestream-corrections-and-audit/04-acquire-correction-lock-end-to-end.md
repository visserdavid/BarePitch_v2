# Acquire Correction Lock End to End

# Purpose
Implement the match edit lock system: the `match_lock` table, `LockService` with acquire/refresh/release logic, the three lock routes, a 2-minute timeout, second-user blocking, expired-lock replacement, and the lock-check guard used before every correction write. This is the concurrency safety layer for finished-match corrections.

# Required Context
See `01-shared-context.md`. This prompt must be complete before `07-correct-finished-match-end-to-end.md` executes, because every correction POST must check and hold a lock before mutating data.

# Required Documentation
- `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md` — section 5.23 (`match_lock`)
- `docs/BarePitch-v2-02-authorization-matrix-v1.0.md` — `match lock: acquire`, `refresh`, `release` rows
- `docs/BarePitch-v2-05-critical-behavior-specifications-v1.0.md` — section 16 (Locking and Concurrency Behavior)
- `docs/BarePitch-v2-08-route-api-specification-v1.0.md` — section 24 (Lock Routes)
- `docs/BarePitch-v2-10-data-integrity-test-scenarios-v1.0.md` — LK-01, LK-02, LK-03, LK-04

# Scope

## Schema: `match_lock` table

If not yet present, create a migration at `database/migrations/NNNN_create_match_locks.sql`:

```sql
CREATE TABLE match_lock (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    match_id   INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    locked_at  DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_match_lock (match_id)
);
```

Rules:
- `UNIQUE KEY (match_id)` enforces at most one lock record per match
- The single row is overwritten (or upserted) on acquire; no history of previous locks is needed
- Soft delete is not used — locks expire naturally; they are not user-facing historical records

## Lock Constants

Create `app/Domain/LockConstants.php`:

```php
<?php

namespace App\Domain;

class LockConstants
{
    /** Lock timeout in seconds. Recommended by docs: 2 minutes. */
    public const TIMEOUT_SECONDS = 120;

    /** Refresh interval in seconds. Recommended by docs: 30 seconds. */
    public const REFRESH_INTERVAL_SECONDS = 30;
}
```

## LockRepository

Create `app/Repositories/LockRepository.php`:

```php
<?php

namespace App\Repositories;

use PDO;

class LockRepository
{
    public function __construct(private PDO $db) {}

    /**
     * Find the current lock row for a match.
     * Returns the row regardless of expiry state; callers must check.
     */
    public function findByMatchId(int $matchId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM match_lock WHERE match_id = :match_id LIMIT 1'
        );
        $stmt->execute([':match_id' => $matchId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Insert a new lock row for a match.
     * Caller must ensure no active lock exists before calling this.
     */
    public function insert(int $matchId, int $userId, int $timeoutSeconds): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO match_lock (match_id, user_id, locked_at, expires_at)
             VALUES (:match_id, :user_id, NOW(), DATE_ADD(NOW(), INTERVAL :timeout SECOND))'
        );
        $stmt->execute([
            ':match_id' => $matchId,
            ':user_id'  => $userId,
            ':timeout'  => $timeoutSeconds,
        ]);
    }

    /**
     * Update an existing lock row — used for acquire-replace and refresh.
     * Sets user_id, locked_at = NOW(), expires_at = NOW() + timeout.
     */
    public function updateLock(int $matchId, int $userId, int $timeoutSeconds): void
    {
        $stmt = $this->db->prepare(
            'UPDATE match_lock
             SET user_id = :user_id,
                 locked_at = NOW(),
                 expires_at = DATE_ADD(NOW(), INTERVAL :timeout SECOND)
             WHERE match_id = :match_id'
        );
        $stmt->execute([
            ':match_id' => $matchId,
            ':user_id'  => $userId,
            ':timeout'  => $timeoutSeconds,
        ]);
    }

    /**
     * Delete the lock row for a match (release).
     */
    public function deleteByMatchId(int $matchId): void
    {
        $stmt = $this->db->prepare('DELETE FROM match_lock WHERE match_id = :match_id');
        $stmt->execute([':match_id' => $matchId]);
    }
}
```

## LockService

Create `app/Services/LockService.php`:

```php
<?php

namespace App\Services;

use App\Domain\LockConstants;
use App\Repositories\LockRepository;
use App\Domain\Exceptions\LockConflictException;
use App\Domain\Exceptions\LockOwnershipException;

class LockService
{
    public function __construct(
        private LockRepository $lockRepository
    ) {}

    /**
     * Attempt to acquire or refresh the edit lock for a match.
     *
     * Acquisition rules:
     * 1. No lock row exists → insert new lock for this user
     * 2. Lock row exists AND is expired (expires_at < NOW()) → replace with new lock for this user
     * 3. Lock row exists AND owned by same user → refresh (extend expires_at)
     * 4. Lock row exists AND owned by different user AND not expired → throw LockConflictException
     *
     * @throws LockConflictException if another user holds an active, non-expired lock
     */
    public function acquireOrRefresh(int $matchId, int $userId): void
    {
        $existing = $this->lockRepository->findByMatchId($matchId);

        if ($existing === null) {
            // Case 1: No lock — acquire fresh
            $this->lockRepository->insert($matchId, $userId, LockConstants::TIMEOUT_SECONDS);
            return;
        }

        $isExpired = strtotime($existing['expires_at']) <= time();

        if ($isExpired) {
            // Case 2: Expired lock — replace regardless of owner
            $this->lockRepository->updateLock($matchId, $userId, LockConstants::TIMEOUT_SECONDS);
            return;
        }

        if ((int) $existing['user_id'] === $userId) {
            // Case 3: Same user holds active lock — refresh
            $this->lockRepository->updateLock($matchId, $userId, LockConstants::TIMEOUT_SECONDS);
            return;
        }

        // Case 4: Different user holds active lock — deny
        throw new LockConflictException(
            'This match is currently being edited by another user.'
        );
    }

    /**
     * Refresh an existing lock owned by the given user.
     * Used by the background refresh interval (every 30 seconds while editing).
     *
     * @throws LockOwnershipException if user does not own the lock
     */
    public function refresh(int $matchId, int $userId): void
    {
        $existing = $this->lockRepository->findByMatchId($matchId);

        if ($existing === null || (int) $existing['user_id'] !== $userId) {
            throw new LockOwnershipException(
                'You do not own the edit lock for this match.'
            );
        }

        $this->lockRepository->updateLock($matchId, $userId, LockConstants::TIMEOUT_SECONDS);
    }

    /**
     * Release the edit lock for a match.
     * Lock owner or administrator may release.
     *
     * @throws LockOwnershipException if the user is not the owner and not an administrator
     */
    public function release(int $matchId, int $userId, bool $isAdministrator): void
    {
        $existing = $this->lockRepository->findByMatchId($matchId);

        if ($existing === null) {
            // No lock to release — idempotent
            return;
        }

        if (!$isAdministrator && (int) $existing['user_id'] !== $userId) {
            throw new LockOwnershipException(
                'You cannot release a lock you do not own.'
            );
        }

        $this->lockRepository->deleteByMatchId($matchId);
    }

    /**
     * Assert that the given user currently holds an active, non-expired lock for a match.
     * Called inside correction transactions before any mutation.
     *
     * @throws LockConflictException if no lock is held by this user
     */
    public function assertOwnsActiveLock(int $matchId, int $userId): void
    {
        $existing = $this->lockRepository->findByMatchId($matchId);

        if ($existing === null) {
            throw new LockConflictException('No edit lock is held for this match.');
        }

        if ((int) $existing['user_id'] !== $userId) {
            throw new LockConflictException(
                'This match is currently being edited by another user.'
            );
        }

        if (strtotime($existing['expires_at']) <= time()) {
            throw new LockConflictException(
                'Your edit lock has expired. Please re-open the correction screen.'
            );
        }
    }
}
```

## Exception Classes

Create the following exception classes if not already present:

`app/Domain/Exceptions/LockConflictException.php`:
```php
<?php
namespace App\Domain\Exceptions;
class LockConflictException extends \RuntimeException {}
```

`app/Domain/Exceptions/LockOwnershipException.php`:
```php
<?php
namespace App\Domain\Exceptions;
class LockOwnershipException extends \RuntimeException {}
```

## Lock Routes

### POST /matches/{match_id}/lock — Acquire or Refresh

**Controller**: `LockController::acquire(int $matchId)`

**Access**: authenticated; user must have edit permission for the match's current state

**CSRF**: required

**Policy check**: `LockPolicy::canAcquire($user, $match, $userTeamRole)` — user must be coach or administrator for this team (same rules as `match lock: acquire` in authorization matrix); `team_manager` is also allowed per the matrix but may not correct finished matches — the lock acquisition itself does not enforce correction permission, but callers of correction routes must check separately.

**Behavior**:
1. Load match, verify team access
2. Verify user has edit permission for the match's current state (for corrections: match must be `finished` and user must be coach or administrator)
3. Call `LockService::acquireOrRefresh($matchId, $userId)`
4. Return JSON success

**Success response**:
```json
{ "ok": true, "data": { "locked": true, "expires_in_seconds": 120 } }
```

**Conflict response** (HTTP 409):
```json
{
  "ok": false,
  "error": {
    "code": "locked",
    "message": "This match is currently being edited by another user."
  }
}
```

### POST /matches/{match_id}/lock/refresh — Refresh Lock

**Controller**: `LockController::refresh(int $matchId)`

**Access**: authenticated; user must own the lock

**CSRF**: required

**Behavior**:
1. Verify user still has edit permission for the match
2. Call `LockService::refresh($matchId, $userId)`
3. Return JSON success

**Success response**:
```json
{ "ok": true, "data": { "refreshed": true, "expires_in_seconds": 120 } }
```

**Ownership failure response** (HTTP 409):
```json
{
  "ok": false,
  "error": { "code": "locked", "message": "You do not own the edit lock for this match." }
}
```

### POST /matches/{match_id}/lock/release — Release Lock

**Controller**: `LockController::release(int $matchId)`

**Access**: authenticated; owner or administrator

**CSRF**: required

**Behavior**:
1. Call `LockService::release($matchId, $userId, $isAdministrator)`
2. Return JSON success or redirect to match page

**Success response**:
```json
{ "ok": true, "data": { "released": true } }
```

## LockPolicy

Create `app/Policies/LockPolicy.php`:

```php
<?php

namespace App\Policies;

class LockPolicy
{
    /**
     * Determines if the user may acquire or refresh a lock for the given match.
     * Must also have edit permission for the current match state.
     */
    public static function canAcquire(array $user, array $match, array $userTeamRole): bool
    {
        if ($user['is_administrator']) {
            return true;
        }
        // Team manager can acquire locks in general, but cannot correct finished matches.
        // That further restriction is enforced by correction-specific policies.
        $allowedRoles = ['coach', 'team_manager'];
        return in_array($userTeamRole['role_key'], $allowedRoles, true)
            && (int) $userTeamRole['team_id'] === (int) $match['team_id'];
    }

    /**
     * Determines if the user may release a given lock.
     */
    public static function canRelease(array $user, array $lock, array $userTeamRole, array $match): bool
    {
        if ($user['is_administrator']) {
            return true;
        }
        return (int) $lock['user_id'] === (int) $user['id'];
    }
}
```

## JavaScript Lock Refresh Client

The correction screen HTML view must include a client-side refresh loop so the lock does not expire while the user is actively editing. Include the following script in `app/Views/corrections/show.php` (created in prompt 07):

```html
<script>
(function () {
    'use strict';

    var matchId = <?php echo (int) $match['id']; ?>;
    var refreshUrl = '/matches/' + matchId + '/lock/refresh';
    var csrfToken = <?php echo json_encode($csrfToken); ?>;
    var refreshIntervalMs = 30000; // 30 seconds per docs

    function refreshLock() {
        fetch(refreshUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            credentials: 'same-origin',
            body: 'csrf_token=' + encodeURIComponent(csrfToken)
        })
        .then(function (response) { return response.json(); })
        .then(function (json) {
            if (!json.ok) {
                // Lock lost or expired — notify user and stop edits
                clearInterval(timer);
                alert('Your editing session has expired. Please reload the page to continue.');
            }
        })
        .catch(function () {
            // Network error — silently retry on next interval
        });
    }

    var timer = setInterval(refreshLock, refreshIntervalMs);

    // Release lock when user navigates away
    window.addEventListener('beforeunload', function () {
        clearInterval(timer);
        // Best-effort release on page unload (fire-and-forget)
        if (navigator.sendBeacon) {
            navigator.sendBeacon(
                '/matches/' + matchId + '/lock/release',
                new URLSearchParams({ csrf_token: csrfToken })
            );
        }
    });
})();
</script>
```

## Lock Check in Correction Transactions

Every correction POST (covered in prompt 07) must call `LockService::assertOwnsActiveLock()` at the start of the correction transaction, before any mutation. This call belongs inside the service method, not in the controller:

```php
// Inside CorrectionService::correctEvent() — before any DB writes
$this->lockService->assertOwnsActiveLock($matchId, $userId);
// ... proceed with correction
```

If `assertOwnsActiveLock()` throws, the caller (controller) catches it and returns a `locked` error. No mutation has occurred.

# Out of Scope
- Correction route implementation (prompt 07)
- Audit logging (prompt 07)
- Score recalculation (prompt 05)
- Locking for live match control (established in v0.5.0/v0.6.0)

# Architectural Rules
- `LockService` owns all lock logic — no raw lock SQL in controllers or correction services
- `LockService::assertOwnsActiveLock()` is called inside service transaction scope — not after commit
- The `UNIQUE KEY (match_id)` on `match_lock` is the database-level enforcement of one-lock-per-match
- Lock refresh routes verify the user still has edit permission before extending the lock — not just ownership
- No silent overwrite: `acquireOrRefresh()` throws `LockConflictException` when blocked; controller maps this to HTTP 409 and returns the `locked` error code
- Lock expiry is checked against the server clock (`time()`) — never trusted from client

# Acceptance Criteria
- LK-01: Match with no lock — coach opens correction screen → lock is assigned to coach (verify DB row)
- LK-02: Coach A holds active lock — Coach B opens correction screen → Coach B receives `locked` error, no lock reassignment
- LK-03: Lock is older than 2 minutes (`expires_at` in the past) — Coach B opens correction screen → lock is reassigned to Coach B
- LK-04: Coach A owns lock — refresh request succeeds → `expires_at` is extended by 120 seconds
- Lock release clears the lock row (verify DB row deleted after release)
- Release by non-owner, non-administrator returns `LockOwnershipException` error
- Correction POST without owning an active lock returns `locked` error and makes no changes to source data
- Lock JS refresh runs every 30 seconds while the correction page is open
- Navigating away from the correction page triggers a best-effort lock release

# Verification
- PHP syntax check all new/modified files
- Test LK-01: no lock in DB → call `POST /matches/{id}/lock` → verify `match_lock` row created with correct `user_id` and future `expires_at`
- Test LK-02: create lock for user A → call `POST /matches/{id}/lock` as user B (active lock) → verify HTTP 409 and `locked` error code
- Test LK-03: create lock with `expires_at = NOW() - 130 seconds` manually → call `POST /matches/{id}/lock` as user B → verify lock reassigned to user B
- Test LK-04: create lock for user A → call `POST /matches/{id}/lock/refresh` as user A → verify `expires_at` updated to approximately `NOW() + 120s`
- Test correction without lock: submit correction POST without acquiring lock → verify no data change and `locked` error returned

# Handoff Note
`05-score-recalculation-after-correction.md` and `06-audit-logging.md` provide the support services. `07-correct-finished-match-end-to-end.md` implements all three correction POST routes. Each of those routes calls `LockService::assertOwnsActiveLock()` as the first step inside the correction transaction. The lock JS refresh script from this prompt must be included in the correction screen view built in prompt 07.

# Create and Share Livestream End to End

# Purpose
Implement the livestream token lifecycle: creation on match start, hash-only storage, the public `GET /live/{token}` HTML route, the manual stop route, and the token rotation route. Establish the security header requirements and rate-limiting preparation for all public livestream endpoints.

# Required Context
See `01-shared-context.md`. The match-start transaction (`LiveMatchService::startMatch()`) must already exist from v0.5.0. This prompt extends that transaction to include livestream token creation and extends the routes layer to expose the public URL.

# Required Documentation
- `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md` — section 5.25 (`livestream_token`), section 5.4 (`team.livestream_hours_after_match`)
- `docs/BarePitch-v2-02-authorization-matrix-v1.0.md` — `livestream: public view`, `livestream: stop`, `livestream: rotate token` rows
- `docs/BarePitch-v2-05-critical-behavior-specifications-v1.0.md` — section 14 (Livestream Synchronization Behavior)
- `docs/BarePitch-v2-08-route-api-specification-v1.0.md` — section 20 (Livestream Routes)
- `docs/BarePitch-v2-10-data-integrity-test-scenarios-v1.0.md` — LS-01, LS-03

# Scope

## Schema: `livestream_token` table

If not yet present, create a migration at `database/migrations/NNNN_create_livestream_tokens.sql`:

```sql
CREATE TABLE livestream_token (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    match_id       INT UNSIGNED NOT NULL,
    token_hash     CHAR(64) NOT NULL,
    issued_at      DATETIME NOT NULL,
    expires_at     DATETIME NULL,
    stopped_at     DATETIME NULL,
    rotated_from_token_id INT UNSIGNED NULL,
    created_by_user_id INT UNSIGNED NOT NULL,
    UNIQUE KEY uq_token_hash (token_hash),
    KEY idx_match_id (match_id)
);
```

Rules enforced at schema level:
- `token_hash CHAR(64)` — SHA-256 hex, never the raw token
- `expires_at` is NULL until set at match finish
- `stopped_at` is NULL until manually stopped
- Only one token per match should be treated as active at any time (enforced in application logic)

## Token Generation Helper

Create `app/Domain/TokenGenerator.php`:

```php
<?php

namespace App\Domain;

class TokenGenerator
{
    /**
     * Generate a cryptographically secure raw token.
     * Returns the raw token (to be included in the public URL once)
     * and its SHA-256 hex hash (to be stored in the database).
     *
     * @return array{raw: string, hash: string}
     */
    public static function generate(): array
    {
        $raw = bin2hex(random_bytes(32)); // 64-character hex string
        $hash = hash('sha256', $raw);
        return ['raw' => $raw, 'hash' => $hash];
    }

    /**
     * Hash an incoming raw token for database lookup.
     */
    public static function hash(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }
}
```

The raw token is placed in the public URL. It is never stored and never re-read from the database.

## LivestreamRepository

Create `app/Repositories/LivestreamRepository.php`:

```php
<?php

namespace App\Repositories;

use PDO;

class LivestreamRepository
{
    public function __construct(private PDO $db) {}

    /**
     * Insert a new livestream token record.
     * @param string $tokenHash SHA-256 hex of the raw token
     */
    public function createToken(
        int $matchId,
        string $tokenHash,
        int $createdByUserId,
        ?int $rotatedFromTokenId = null
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO livestream_token
             (match_id, token_hash, issued_at, expires_at, stopped_at, rotated_from_token_id, created_by_user_id)
             VALUES (:match_id, :token_hash, NOW(), NULL, NULL, :rotated_from, :user_id)'
        );
        $stmt->execute([
            ':match_id'    => $matchId,
            ':token_hash'  => $tokenHash,
            ':rotated_from' => $rotatedFromTokenId,
            ':user_id'     => $createdByUserId,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Find the active (non-stopped, non-expired) token row for a match.
     */
    public function findActiveByMatchId(int $matchId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM livestream_token
             WHERE match_id = :match_id
               AND stopped_at IS NULL
               AND (expires_at IS NULL OR expires_at > NOW())
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([':match_id' => $matchId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Find a token row by its SHA-256 hash.
     * Returns the row regardless of expiry/stopped state; callers must validate.
     */
    public function findByHash(string $tokenHash): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM livestream_token WHERE token_hash = :hash LIMIT 1'
        );
        $stmt->execute([':hash' => $tokenHash]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Set stopped_at on the token with the given ID.
     */
    public function stopToken(int $tokenId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE livestream_token SET stopped_at = NOW() WHERE id = :id'
        );
        $stmt->execute([':id' => $tokenId]);
    }

    /**
     * Set expires_at on all tokens for a match.
     * Called when match finishes.
     */
    public function setExpiresAtForMatch(int $matchId, string $expiresAt): void
    {
        $stmt = $this->db->prepare(
            'UPDATE livestream_token
             SET expires_at = :expires_at
             WHERE match_id = :match_id AND expires_at IS NULL'
        );
        $stmt->execute([':expires_at' => $expiresAt, ':match_id' => $matchId]);
    }
}
```

## Integration with LiveMatchService::startMatch()

Inside the existing `LiveMatchService::startMatch()` transaction, after setting `status = active`, add the following steps. The transaction already exists; these steps are appended before `$this->db->commit()`:

```php
// Step N: Create livestream token (inside existing transaction)
$token = \App\Domain\TokenGenerator::generate();
$this->livestreamRepository->createToken(
    matchId: $matchId,
    tokenHash: $token['hash'],
    createdByUserId: $userId
);

// Store the raw token temporarily so the controller can construct the public URL.
// Use a service-level property or return value — never persist the raw token.
$this->lastLivestreamToken = $token['raw'];
```

The raw token is then exposed to the controller via a getter `getLivestreamToken(): string` so the coach can be shown the public link. After this method returns, the raw token should be discarded — it is not stored anywhere.

## Integration with LiveMatchService::finishMatch()

Inside the existing `LiveMatchService::finishMatch()` transaction, after setting `status = finished` and `finished_at`:

```php
// Calculate expiration: finished_at + configured team hours (1–72)
$team = $this->teamRepository->findById($match['team_id']);
$hours = max(1, min(72, (int) $team['livestream_hours_after_match']));
$expiresAt = date('Y-m-d H:i:s', strtotime($match['finished_at']) + ($hours * 3600));

$this->livestreamRepository->setExpiresAtForMatch($matchId, $expiresAt);
```

This ensures `expires_at` is always set atomically with match finish. The maximum enforced limit is 72 hours; the configured default is 24 hours.

## LivestreamService

Create `app/Services/LivestreamService.php`:

```php
<?php

namespace App\Services;

use App\Domain\TokenGenerator;
use App\Repositories\LivestreamRepository;
use App\Repositories\MatchRepository;
use PDO;

class LivestreamService
{
    public function __construct(
        private PDO $db,
        private LivestreamRepository $livestreamRepository,
        private MatchRepository $matchRepository
    ) {}

    /**
     * Validate a raw public token and return the token row if valid.
     * Returns null and the caller must show a generic failure if invalid.
     *
     * Validity rules:
     * - token row exists
     * - stopped_at IS NULL
     * - expires_at IS NULL OR expires_at > NOW()
     *
     * @return array|null The token row, or null if invalid
     */
    public function validatePublicToken(string $rawToken): ?array
    {
        $hash = TokenGenerator::hash($rawToken);
        $row = $this->livestreamRepository->findByHash($hash);

        if ($row === null) {
            return null;
        }
        if ($row['stopped_at'] !== null) {
            return null;
        }
        if ($row['expires_at'] !== null && strtotime($row['expires_at']) <= time()) {
            return null;
        }
        return $row;
    }

    /**
     * Manually stop the active livestream token for a match.
     * Called by coach or administrator.
     */
    public function stopLivestream(int $matchId): void
    {
        $token = $this->livestreamRepository->findActiveByMatchId($matchId);
        if ($token !== null) {
            $this->livestreamRepository->stopToken($token['id']);
        }
        // If no active token found, the stop is a no-op — idempotent behavior.
    }

    /**
     * Rotate the livestream token: stop the current one and issue a new one.
     * Requires recent authentication (enforced by policy before this call).
     *
     * @return string The new raw token (to be shown to the coach once)
     */
    public function rotateToken(int $matchId, int $userId): string
    {
        $this->db->beginTransaction();
        try {
            $oldToken = $this->livestreamRepository->findActiveByMatchId($matchId);
            $oldTokenId = $oldToken ? $oldToken['id'] : null;

            if ($oldToken !== null) {
                $this->livestreamRepository->stopToken($oldToken['id']);
            }

            $newToken = TokenGenerator::generate();
            $this->livestreamRepository->createToken(
                matchId: $matchId,
                tokenHash: $newToken['hash'],
                createdByUserId: $userId,
                rotatedFromTokenId: $oldTokenId
            );

            $this->db->commit();
            return $newToken['raw'];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
```

## Routes

### GET /live/{token}

**Controller**: `app/Http/Controllers/LivestreamController.php`, method `showPublic(string $rawToken)`

**Access**: public — no session check

**Behavior**:
1. Hash `$rawToken`
2. Call `LivestreamService::validatePublicToken($rawToken)`
3. If null: send 404 HTTP status, render generic unavailable view — no detail about token existence
4. If valid: load match data (score, active_phase, timeline events) from repositories
5. Render public livestream HTML view

**Required security headers** (sent on every response from this route, including failure):
```
Cache-Control: no-store
Referrer-Policy: no-referrer
X-Robots-Tag: noindex, nofollow
```

**Rate limiting**: add a comment block in the controller marking the rate-limit integration point. If a rate-limiting middleware or helper exists in the project, invoke it here. If not, document the hook:

```php
// RATE LIMIT: This public endpoint must be rate-limited by IP before production deployment.
// Integration point: call RateLimiter::check('livestream_view', $clientIp, limit: 60, windowSeconds: 60)
// if RateLimiter is available. If not present, add it before v1.0.0 hardening (v0.9.0).
```

**Failure view** (`app/Views/livestream/unavailable.php`):
- Shows a plain message such as "This link is not available." or "This livestream is unavailable."
- Must not reveal whether the token exists, whether it expired, or whether it was stopped
- Sends HTTP 404 status

### POST /matches/{match_id}/livestream/stop

**Controller**: `LivestreamController::stop(int $matchId)`

**Access**: authenticated — coach or administrator only (team scope)

**CSRF**: required

**Policy check**: `LivestreamPolicy::canStop($user, $match)` — returns true if user is coach for this team or is administrator

**Behavior**:
1. Load match, verify team access
2. Check policy
3. Call `LivestreamService::stopLivestream($matchId)`
4. Redirect to match detail page with success flash

**Authorization matrix row**: livestream stop — Allow for coach and administrator; Deny for trainer, team_manager, public.

### POST /matches/{match_id}/livestream/rotate-token

**Controller**: `LivestreamController::rotateToken(int $matchId)`

**Access**: authenticated — coach or administrator only (team scope)

**CSRF**: required

**Recent auth required**: yes — per authorization matrix (`livestream: rotate token` requires recent auth)

**Policy check**: `LivestreamPolicy::canRotateToken($user, $match)` — allow if coach or administrator and recent auth confirmed

**Behavior**:
1. Load match, verify team access
2. Check policy including recent-auth guard
3. Call `LivestreamService::rotateToken($matchId, $userId)`
4. Present the new raw token URL to the coach (shown once, not stored)
5. Redirect or re-render with the new public link displayed

## LivestreamPolicy

Create `app/Policies/LivestreamPolicy.php`:

```php
<?php

namespace App\Policies;

class LivestreamPolicy
{
    public static function canStop(array $user, array $match, array $userTeamRole): bool
    {
        if ($user['is_administrator']) {
            return true;
        }
        return $userTeamRole['role_key'] === 'coach'
            && $userTeamRole['team_id'] === $match['team_id'];
    }

    public static function canRotateToken(array $user, array $match, array $userTeamRole, bool $hasRecentAuth): bool
    {
        if (!$hasRecentAuth) {
            return false;
        }
        if ($user['is_administrator']) {
            return true;
        }
        return $userTeamRole['role_key'] === 'coach'
            && $userTeamRole['team_id'] === $match['team_id'];
    }
}
```

## Security Header Helper

Create or extend `app/Core/HttpHeaders.php` to include a method for applying public livestream headers:

```php
public static function applyPublicLivestreamHeaders(): void
{
    header('Cache-Control: no-store');
    header('Referrer-Policy: no-referrer');
    header('X-Robots-Tag: noindex, nofollow');
}
```

Call this at the top of both `LivestreamController::showPublic()` and `LivestreamController::showData()` (the polling endpoint) before any output.

# Out of Scope
- Public polling JSON endpoint (covered in prompt 03)
- Correction routes (covered in prompt 07)
- Lock acquisition for corrections (covered in prompt 04)
- Match edit during live phase lock management (covered in prior milestones)

# Architectural Rules
- Raw tokens are generated in `LivestreamService` and `TokenGenerator` — never in controllers or repositories
- Only `CHAR(64)` SHA-256 hash is written to `livestream_token.token_hash` — raw tokens are ephemeral
- Token validation always re-hashes the incoming raw token and looks up by hash — no plaintext comparison
- `LivestreamService::stopLivestream()` is idempotent — calling it when no active token exists is safe
- Token rotation runs in a transaction: old stop + new insert are atomic
- Security headers must be sent before HTML output, including on failure responses

# Acceptance Criteria
- Match start transaction creates a `livestream_token` row with a non-plaintext hash, `issued_at` set, `expires_at` NULL, `stopped_at` NULL
- Public URL `/live/{rawToken}` resolves and shows the livestream page when token is valid
- `GET /live/{token}` sends `Cache-Control: no-store`, `Referrer-Policy: no-referrer`, `X-Robots-Tag: noindex, nofollow`
- `GET /live/{invalidToken}` returns 404 with generic message — no hint about token existence
- Coach can stop livestream; subsequent public access returns 404 with generic message
- Token rotation issues a new token and immediately invalidates the old one
- Token rotation is blocked without recent authentication
- Trainer and team_manager cannot stop or rotate the livestream (403)
- `livestream_token.token_hash` column in database never contains the raw token value

# Verification
- PHP syntax check all new/modified files
- Query `livestream_token` after match start: verify `token_hash != rawToken` (i.e., it is a 64-character SHA-256 hex, not the raw value)
- Test `GET /live/{validToken}` — verify HTTP 200 and required headers in response
- Test `GET /live/{expiredToken}` — verify HTTP 404 and generic message
- Test `GET /live/{stoppedToken}` — verify HTTP 404 and generic message
- Test `POST /matches/{id}/livestream/stop` as trainer — verify 403
- Test `POST /matches/{id}/livestream/rotate-token` without recent auth — verify blocked
- Test token rotation: after rotation, old raw token returns 404, new raw token returns 200

# Handoff Note
`03-view-public-livestream-end-to-end.md` implements the polling endpoint `GET /live/{token}/data` and the JavaScript polling loop on the public page. `04-acquire-correction-lock-end-to-end.md` implements the lock table and acquisition logic used by correction routes.

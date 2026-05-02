# Rate Limiting for Login Token Requests

# Purpose
Implement basic rate limiting for `POST /login/request` to prevent token flooding and enumeration attacks. This does not require Redis — use a simple database table.

# Required Context
See `01-shared-context.md`. Login routes exist from `02-request-and-consume-magic-link-end-to-end.md`.

# Required Documentation
- `docs/BarePitch-v2-12-ai-implementation-rules-v1.0.md` — rate limiting requirements
- `docs/BarePitch-v2-03-system-architecture-v1.0.md` — security baseline

# Scope

## Database table

Add a migration in `database/migrations/` for `rate_limit_attempts`:
```sql
CREATE TABLE rate_limit_attempts (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    limit_key    VARCHAR(255) NOT NULL,  -- e.g., "login:127.0.0.1" or "login:user@example.com"
    window_start DATETIME NOT NULL,
    attempts     INT UNSIGNED NOT NULL DEFAULT 1,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_limit_key_window (limit_key, window_start)
);
```

## `app/Services/RateLimitService.php`

```php
class RateLimitService {
    private const MAX_ATTEMPTS = 5;       // per window; use docs value if specified
    private const WINDOW_SECONDS = 900;   // 15 minutes; use docs value if specified

    public function isAllowed(string $key): bool {
        $this->pruneOldAttempts($key);
        $count = $this->repository->countAttempts($key, $this->windowStart());
        return $count < self::MAX_ATTEMPTS;
    }

    public function recordAttempt(string $key): void {
        $this->repository->insertAttempt($key, $this->windowStart());
    }

    private function windowStart(): string {
        return date('Y-m-d H:i:s', time() - self::WINDOW_SECONDS);
    }

    private function pruneOldAttempts(string $key): void {
        $this->repository->deleteOldAttempts($key, $this->windowStart());
    }
}
```

## `app/Repositories/RateLimitRepository.php`

Implement:
- `countAttempts(string $key, string $since): int`
- `insertAttempt(string $key, string $windowStart): void`
- `deleteOldAttempts(string $key, string $before): void`

All using PDO prepared statements.

## Rate limit keys

Use two keys for login requests (check both):
- IP-based: `"login_ip:" . $_SERVER['REMOTE_ADDR']`
- Email-based: `"login_email:" . strtolower($email)` — only if email was provided and valid format

## Integration with `AuthController::requestToken()`

Before generating any token:
```php
$ipKey = 'login_ip:' . $_SERVER['REMOTE_ADDR'];
$emailKey = 'login_email:' . strtolower($email);

if (!$rateLimitService->isAllowed($ipKey) || !$rateLimitService->isAllowed($emailKey)) {
    // Return generic "too many requests" — same format as the normal generic response
    // Do NOT reveal the limit details or that rate limiting was triggered
    render('auth/login-request-sent.php'); // same view as normal response
    return;
}

$rateLimitService->recordAttempt($ipKey);
$rateLimitService->recordAttempt($emailKey);
// ... proceed with token generation
```

**Important**: the rate-limit exceeded response must be indistinguishable from the normal generic response ("If this email address is registered, a login link has been sent."). Do not return a 429 status or any message that reveals rate limiting.

## Configuration
Define constants in `config/app.php`:
- `RATE_LIMIT_LOGIN_MAX_ATTEMPTS` — default 5
- `RATE_LIMIT_LOGIN_WINDOW_SECONDS` — default 900 (15 min)

Use the values from the docs if they differ from these defaults.

# Out of Scope
- Rate limiting for public livestream endpoints (v0.8.0)
- Rate limiting for any routes other than login request
- Redis-based rate limiting

# Architectural Rules
- `RateLimitService` is called from `AuthController` before token generation
- `RateLimitService` must not be called after the token generation step (record attempt only when actually attempting, not after)
- All SQL in `RateLimitRepository` uses PDO prepared statements

# Acceptance Criteria
- After 5 rapid `POST /login/request` calls from the same IP within 15 minutes, subsequent calls return the same generic "login link sent" response (rate limited silently)
- Attempts are stored and counted in the `rate_limit_attempts` table
- Attempts older than the window are pruned on each check
- The response when rate-limited is identical to the normal response — no distinguishing status code or message

# Verification
- PHP syntax check all new files
- Run migration: confirm `rate_limit_attempts` table created
- Submit 6 rapid POST /login/request calls from the same IP — verify 6th call is handled silently
- Query DB after test: confirm attempt rows present

# Handoff Note
`07-testing-and-verification.md` adds the automated test suite covering all v0.2.0 authentication behaviors.

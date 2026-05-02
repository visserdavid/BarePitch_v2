# Request and Consume Magic Link End to End

# Purpose
Implement magic-link token generation, secure hashing, database storage, the login request route, and the login callback route. This establishes the core authentication credential exchange.

# Required Context
See `01-shared-context.md`. The v0.1.0 schema and PDO connection are already in place. A `users` table exists. A `magic_login_tokens` table (or equivalent per `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md`) must exist — check the schema doc for the exact table and column names.

# Required Documentation
- `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md` — token table schema, token expiry duration
- `docs/BarePitch-v2-08-route-api-specification-v1.0.md` — login request and callback route specs
- `docs/BarePitch-v2-12-ai-implementation-rules-v1.0.md` — security requirements

# Scope

## Routes
- `GET /login` — show login request form (email input field, CSRF token, submit button)
- `POST /login/request` — accept email; generate and store hashed token; return generic response
- `GET /login/callback` — validate token; on success create session and redirect; on failure show generic error

## POST /login/request behavior
1. Validate email format server-side (use `filter_var($email, FILTER_VALIDATE_EMAIL)`)
2. Look up user by email in `users` table
3. Regardless of whether the user exists: return generic "If this email address is registered, a login link has been sent." — never reveal whether the email is registered
4. If user exists:
   a. Generate cryptographically secure random token: `$token = bin2hex(random_bytes(32))`
   b. Compute hash: `$hash = hash('sha256', $token)`
   c. Compute expiry timestamp: current time + token lifetime (per docs; typically 15–60 minutes — use the value from docs)
   d. Store in `magic_login_tokens`: `user_id`, `token_hash`, `expires_at`, `used_at` (null), `created_at`
   e. In `APP_ENV=local`: write the full login URL to PHP error log: `error_log("DEV LOGIN URL: " . $loginUrl)`
   f. In production/staging: log the request but do NOT log the raw token
5. CSRF required on this POST
6. Invalidate or limit outstanding tokens per user if docs specify (check docs)

## GET /login/callback behavior
1. Read `token` from query string; if missing, show generic failure
2. Compute `$hash = hash('sha256', $token)`
3. Look up matching hash in `magic_login_tokens` where `token_hash = :hash`
4. Validate (all failures return the same generic message — do not reveal which check failed):
   - Row exists
   - `used_at` is null
   - `expires_at` > now
5. If valid:
   a. Mark token used: `UPDATE magic_login_tokens SET used_at = NOW() WHERE id = :id`
   b. Load the associated user
   c. Create session (session creation implemented in `03-maintain-session-and-logout-end-to-end.md` — stub a `SessionHelper::createSession($user)` call here)
   d. Redirect to `/` or the originally requested URL
6. If invalid: render generic error page — "This login link is invalid or has expired."

## Classes to implement
- `app/Http/Controllers/AuthController.php` — `showLoginForm()`, `requestToken()`, `handleCallback()`
- `app/Http/Requests/LoginRequest.php` — validates email field
- `app/Services/AuthService.php` — `generateToken(User $user): string`, `validateToken(string $token): ?User`
- `app/Repositories/AuthRepository.php` — `findUserByEmail(string $email): ?array`, `storeTokenHash(int $userId, string $hash, string $expiresAt): void`, `findUserByTokenHash(string $hash): ?array`, `markTokenUsed(int $tokenId): void`

## Token security
- Store only the hash; never the raw token
- Use `hash_equals($storedHash, $computedHash)` for constant-time comparison in `validateToken`
- Never include the raw token in any log output outside of `APP_ENV=local`

# Out of Scope
- Session management and cookie configuration (prompt 03)
- Team context and role loading (prompt 04)
- Route protection middleware (prompt 05)
- Rate limiting (prompt 06)
- Email delivery infrastructure (use local log in dev; real email delivery is a post-MVP operational concern)

# Architectural Rules
- `AuthService` owns all token generation and validation logic
- `AuthController` calls `AuthService` — never `AuthRepository` directly
- No raw SQL in the controller
- CSRF check happens before any controller logic

# Acceptance Criteria
- `POST /login/request` with a valid registered email creates a hashed row in `magic_login_tokens`
- `POST /login/request` does not reveal whether the email is registered (same response for registered and unregistered)
- `GET /login/callback` with a valid, unexpired, unused token calls `SessionHelper::createSession()` and redirects
- `GET /login/callback` with a used token returns generic failure
- `GET /login/callback` with an expired token returns generic failure
- `GET /login/callback` with a non-existent token returns generic failure
- The `token_hash` column in the database contains the SHA-256 hash, never the raw token

# Verification
- PHP syntax check all new PHP files: `php -l app/Http/Controllers/AuthController.php` etc.
- Inspect database row after `POST /login/request` — confirm `token_hash` is a 64-char hex string (SHA-256)
- Manually test callback with valid token (from dev log) — confirm redirect
- Manually test callback with the same token a second time — confirm generic failure
- Manually test callback with a modified/fake token — confirm generic failure

# Handoff Note
`03-maintain-session-and-logout-end-to-end.md` implements `SessionHelper::createSession()` (stubbed here), logout, and cookie security configuration that the callback route depends on.

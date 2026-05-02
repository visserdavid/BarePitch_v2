# Security Hardening Review — v0.9.0

# Purpose
Perform a systematic, item-by-item security audit of the entire codebase as built through v0.8.0 and the earlier prompts in this bundle. Fix every gap found. Document any gap that cannot be fixed within this milestone as a tracked issue. The output of this prompt is a codebase that passes every item in the security checklist from `01-shared-context.md`.

---

# Required Context
See `01-shared-context.md`. Prompts 02 and 03 must be complete. The security checklist in the shared context (items S-01 through S-14) is the authoritative audit list.

---

# Required Documentation
- `docs/BarePitch-v2-02-authorization-matrix-v1.0.md` — which routes are write routes; which roles are allowed
- `docs/BarePitch-v2-03-system-architecture-v1.0.md` — layer rules; security responsibilities per layer
- `docs/BarePitch-v2-05-critical-behavior-specifications-v1.0.md` — magic-link, session, and token security rules
- `docs/BarePitch-v2-08-route-api-specification-v1.0.md` — complete route list with methods
- `docs/BarePitch-v2-12-ai-implementation-rules-v1.0.md` — security implementation rules

---

# Scope

## Audit Method

Work through each section below in order. For each item:
1. Identify every code location relevant to the item
2. Verify the item passes or fails
3. If it fails: fix it immediately
4. Record the result in a written audit log (a comment block at the top of a file named `SECURITY-AUDIT.md` in the project root, or inline in the relevant file as a `// AUDIT: PASS` or `// AUDIT: FIX:` comment)

Do not leave a failing item unfixed unless it is genuinely out of scope for this milestone (e.g., requires a new infrastructure component). In that case, open a GitHub issue and record the issue number.

---

## S-01 — CSRF on All Write Routes

**Target**: every `POST`, `PUT`, `PATCH`, `DELETE` route in the application.

Steps:
1. Enumerate all routes from `app/routes.php` (or equivalent router file)
2. For each write route, verify the CSRF middleware runs before the controller method
3. Verify the CSRF middleware checks `$_POST['_csrf']` (or request header equivalent) against a session-stored token using `hash_equals()`
4. Verify all write-route HTML forms include `<input type="hidden" name="_csrf" value="...">` rendered from a helper

**Common gaps to check**:
- AJAX form submissions that construct POST requests in JavaScript — do they include the CSRF token?
- Any route added in a later milestone that may have been created without going through the CSRF middleware
- The `POST /login` (magic-link request) route — does it need CSRF? Per spec, the login form should include CSRF to prevent CSRF-triggered login requests

**Fix pattern** (if CSRF middleware is missing from a route):
```php
// In the router registration, ensure the csrf middleware group wraps the route:
$router->post('/path', [Controller::class, 'method'], ['middleware' => ['auth', 'csrf']]);
```

If the CSRF middleware itself has a bug (e.g., uses `===` instead of `hash_equals()`), fix it:
```php
// Correct:
if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
    http_response_code(403);
    render('errors/403.php', ['message' => __('error.csrf')]);
    exit;
}
```

---

## S-02 — Policy Checks on All Write Routes

**Target**: every controller method that handles a state-changing request.

Steps:
1. For each write route, open the corresponding controller method
2. Verify a policy call is the first operation after request extraction (before calling any service)
3. Verify the policy call returns false for roles that should not have access, based on the authorization matrix

**Common gaps to check**:
- Controller methods added quickly in later milestones that call the service directly without a policy check
- Policy classes that always return `true` as a stub
- Policy checks that only check authentication but not role authorization

**Fix pattern**:
```php
public function update(int $id): void {
    if (!MatchCorrectionPolicy::canCorrect()) {  // <-- must be first
        http_response_code(403);
        render('errors/403.php');
        return;
    }
    // ... rest of method
}
```

---

## S-03 — Prepared Statements Throughout

**Target**: every SQL query in every Repository file and any other file that touches the database.

Steps:
1. Search all PHP files for patterns indicating raw string interpolation in SQL:
   ```bash
   grep -rn "\$.*WHERE\|WHERE.*\$\|INTO.*\$\|SET.*\$\|VALUES.*\$" app/Repositories/
   grep -rn "query(\"" app/
   ```
2. For every result, verify the query uses named parameters (`:param`) or positional parameters (`?`) with `$stmt->bindValue()` or `$stmt->execute([$value])`
3. Fix any query that uses string interpolation

**Fix pattern** (if string interpolation found):
```php
// WRONG:
$stmt = $this->db->query("SELECT * FROM players WHERE team_id = $teamId");

// CORRECT:
$stmt = $this->db->prepare("SELECT * FROM players WHERE team_id = :team_id");
$stmt->execute([':team_id' => $teamId]);
```

Also check:
- `LIMIT` and `OFFSET` clauses — these must use cast-to-int for integer values, not string parameters
- `ORDER BY` clauses with user-supplied column names — these cannot use PDO parameters; use an allowlist of valid column names

---

## S-04 — No Stack Traces Visible to Users

**Target**: the error handler and PHP configuration.

Steps:
1. Verify `display_errors = 0` is set in production (in `php.ini`, `.htaccess`, or the bootstrap)
2. Verify `error_reporting` logs to a file but does not output to the browser
3. Verify the global error handler renders a generic error page (500.php) for uncaught exceptions, not a stack trace
4. Verify that caught domain exceptions (e.g., `PreparationValidationException`) render a user-friendly error message without PHP file paths or line numbers

**Fix pattern** (in `public/index.php` or `app/bootstrap.php`):
```php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/php_errors.log');

set_exception_handler(function (\Throwable $e) {
    error_log($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    render('errors/500.php');
    exit;
});
```

Also verify: does any view file contain `var_dump()`, `print_r()`, or `echo $e->getMessage()` with stack trace output left from debugging? Remove all such calls.

---

## S-05 — Secure Session Settings

**Target**: session configuration in `app/bootstrap.php` or `app/Http/Session.php`.

Verify all of these are set before `session_start()`:

```php
session_set_cookie_params([
    'lifetime' => 0,              // browser-session cookie; absolute lifetime enforced server-side
    'path'     => '/',
    'domain'   => '',             // current domain only
    'secure'   => isHttps(),      // true on HTTPS
    'httponly' => true,           // no JS access
    'samesite' => 'Lax',         // CSRF mitigation
]);
ini_set('session.name', 'bp_sid');               // non-default session name
ini_set('session.use_strict_mode', '1');          // reject unrecognized session IDs
ini_set('session.gc_maxlifetime', '86400');       // PHP GC lifetime; server-side absolute enforced separately
```

**Idle timeout**: verify the session handler checks `$_SESSION['last_activity']` and invalidates the session if the idle threshold is exceeded. Per spec (check `docs/BarePitch-v2-05-critical-behavior-specifications-v1.0.md` for the exact values):
```php
$idleLimit = 60 * 30; // 30 minutes — confirm against spec
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $idleLimit) {
    session_unset();
    session_destroy();
    redirect('/login?reason=idle');
}
$_SESSION['last_activity'] = time();
```

**Absolute timeout**: verify the session handler checks `$_SESSION['created_at']` and invalidates after the absolute limit (check spec for exact value).

**Session ID regeneration on login**: verify `session_regenerate_id(true)` is called immediately after successful magic-link consumption.

---

## S-06 — Magic Links Are One-Time and Expire

**Target**: `app/Services/AuthService.php` and the callback handler.

Verify:
1. On token consume: `used_at` is set to the current timestamp in the same transaction that logs the user in
2. If `used_at IS NOT NULL`: return generic failure (do not log in)
3. If `expires_at < NOW()`: return generic failure
4. If token hash not found: return generic failure
5. All three failure cases return the exact same HTTP response and message

**Fix pattern** (if the three failure paths return different messages):
```php
$genericFailure = function () {
    http_response_code(400);
    render('auth/login.php', ['error' => __('auth.link_expired')]);
    exit;
};

if (!$token) $genericFailure();
if ($token['used_at'] !== null) $genericFailure();
if (strtotime($token['expires_at']) < time()) $genericFailure();
```

---

## S-07 — Tokens Stored Hashed

**Target**: `magic_login_tokens` table insert and lookup code.

Verify:
1. `AuthService::generateToken()` stores `hash('sha256', $rawToken)` in `token_hash`, never the raw token
2. `AuthService::validateToken($rawToken)` hashes the input before querying: `WHERE token_hash = :hash`
3. The raw token appears only in the email link and in the return value of `generateToken()` — it is never logged or stored anywhere else
4. No log statement, error message, or debug output anywhere prints the raw token

```bash
grep -rn "token" app/ | grep -v "token_hash\|sha256\|hash_equals" | grep "log\|error_log\|var_dump\|print_r"
```
Expected: zero results.

---

## S-08 — Rate Limiting on Login and Livestream Endpoints

**Target**: `POST /login` (magic-link request) and `GET /livestream/{token}` (public endpoint).

Verify rate limiting is implemented using a persistent store (database table or file-based counter) that:
1. Tracks request counts per IP address (or per email for login attempts)
2. Enforces a configurable threshold (check spec for values)
3. Returns HTTP 429 when the threshold is exceeded
4. Resets the counter after a configurable window

If rate limiting exists but is only in-memory (per-request), fix it to use persistent storage. In-memory rate limiting resets on every request and provides no real protection.

**Database-based rate limiting** (acceptable for shared hosting):
```sql
CREATE TABLE IF NOT EXISTS rate_limit_attempts (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    action       VARCHAR(32) NOT NULL,   -- 'login' or 'livestream'
    identifier   VARCHAR(45) NOT NULL,   -- IP address or email
    attempted_at DATETIME NOT NULL DEFAULT NOW(),
    INDEX idx_action_identifier (action, identifier, attempted_at)
);
```

Cleanup old rows during each check (or via a scheduled task):
```php
$this->db->prepare(
    "DELETE FROM rate_limit_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL :window SECOND)"
)->execute([':window' => $windowSeconds]);
```

---

## S-09 — Public Token Pages: no-store and noindex

**Target**: the public livestream page handler (e.g., `app/Http/Controllers/LivestreamPublicController.php`).

Verify these headers are sent before any output on the public livestream route:
```php
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow');
```

These headers must be sent regardless of whether the token is valid or expired. They must not be conditional on authentication state.

---

## S-10 — Generic Messaging for Public Token Failures

**Target**: every code path that handles an invalid, expired, or used livestream token.

Verify:
1. A valid token shows the livestream page
2. An invalid token shows a generic "This livestream has ended." message (or equivalent — check `__('livestream.expired')`)
3. An expired token shows the same generic message
4. A used/stopped token shows the same generic message
5. None of the failure messages say "token not found", "token expired", or "token already used" — all must be identical generic messages

---

## S-11 — Dev Login Gated on APP_ENV=local

**Target**: any temporary developer login bypass from v0.1.0.

Verify the bypass is wrapped:
```php
if ($_ENV['APP_ENV'] === 'local') {
    // dev bypass code
}
```

Also verify:
1. The condition checks `$_ENV['APP_ENV']` or `getenv('APP_ENV')`, not a hardcoded string or a comment
2. There is no other path that bypasses authentication (e.g., a hidden `?dev=1` query parameter, a magic cookie, or a hardcoded user ID)

```bash
grep -rn "dev\|bypass\|skip.*auth\|no.*auth\|debug.*login" app/ --include="*.php" -i
```

Review every result. Any bypass not gated on `APP_ENV=local` is a critical finding.

---

## S-12 — HSTS on Production HTTPS

**Target**: the response header middleware or `public/index.php`.

Verify:
```php
if (($_ENV['APP_ENV'] ?? '') === 'production' && isHttps()) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
```

If `isHttps()` does not exist, implement it:
```php
function isHttps(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? 80) == 443);
}
```

---

## S-13 — Session Idle and Absolute Lifetime

Already covered in S-05. Verify the session handler enforces both idle and absolute timeouts, and that the exact time values match the specification in `docs/BarePitch-v2-05-critical-behavior-specifications-v1.0.md`.

---

## S-14 — Invalid Access Attempts Fail Safely

**Target**: the route protection middleware and all 403/404 handlers.

Verify:
1. An unauthenticated request to a protected route returns a redirect to `/login`, not a 403 with a detailed message
2. An authenticated request to a resource the user does not own (wrong team) returns 403 with the generic error message, not a 500 or a data leak
3. A request to a non-existent route returns 404 with the generic error view
4. No error page reveals file paths, SQL queries, or internal application state

---

## Security Headers Review (All Responses)

In addition to the S-12 HSTS item, verify these headers are sent on every HTML response:
```php
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
```

Add them in a global response middleware or in the bootstrap, applied before any output.

These three headers are minimal and appropriate for shared hosting with no CSP infrastructure.

**Do NOT add** a `Content-Security-Policy` header in this milestone unless you can enumerate every inline script and style already in use — an incorrect CSP breaks the application. Leave CSP as a post-MVP improvement.

---

## Audit Log File

Create `SECURITY-AUDIT.md` in the project root documenting the results:

```markdown
# Security Audit — v0.9.0

Date: [date]

| ID   | Item                                | Result | Notes / Fix Applied |
|------|-------------------------------------|--------|---------------------|
| S-01 | CSRF on all write routes            | PASS   |                     |
| S-02 | Policy checks on all write routes   | PASS   |                     |
| ...  | ...                                 | ...    |                     |
```

For any item marked `FIX`, describe what was changed. For any item that requires a follow-up issue, record the issue number.

---

# Out of Scope

- Content Security Policy (CSP) header
- Subresource Integrity (SRI) for static assets
- Two-factor authentication
- OAuth or SSO integration
- Penetration testing or vulnerability scanning tools
- Dependency vulnerability scanning (no Composer dependencies beyond PHPUnit)
- Session encryption at rest

---

# Architectural Rules

- Security header middleware must run before any controller logic and before any output
- Rate limiting must use persistent storage — not in-memory state
- All token comparison must use `hash_equals()` — never `===`
- Error handlers must not re-throw exceptions to the browser
- Policy checks go in the controller, not the service; the service may throw on unexpected states but must not perform authorization decisions

---

# Acceptance Criteria

All 14 items in the security checklist (S-01 through S-14) pass as verified by the audit log.

In addition:
- `X-Frame-Options: DENY` present on all HTML responses
- `X-Content-Type-Options: nosniff` present on all HTML responses
- `Referrer-Policy: strict-origin-when-cross-origin` present on all HTML responses
- No `var_dump`, `print_r`, or stack trace output reachable by a normal browser request
- `SECURITY-AUDIT.md` exists in the project root with results for all 14 items

---

# Verification

1. PHP syntax check:
   ```bash
   find app/ -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"
   ```
2. Grep for string interpolation in SQL:
   ```bash
   grep -rn "query(\"" app/Repositories/ app/Services/
   ```
3. Grep for raw token storage:
   ```bash
   grep -rn "token" app/ | grep -v "token_hash\|sha256\|_csrf"
   ```
4. Check response headers manually using browser developer tools or `curl -I`:
   ```bash
   curl -I http://localhost:8000/players
   ```
   Expected headers: `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`.
5. Simulate expired magic-link: set `expires_at` to the past in the DB and try the callback URL. Confirm generic error.
6. Confirm `SECURITY-AUDIT.md` is complete with no `FAIL` rows.

---

# Handoff Note

After this prompt, every item in the security checklist passes. `05-data-consistency-review.md` audits derived data correctness. Any security fix that touched a service or repository may have implications for data consistency — read both prompts before concluding either is done.

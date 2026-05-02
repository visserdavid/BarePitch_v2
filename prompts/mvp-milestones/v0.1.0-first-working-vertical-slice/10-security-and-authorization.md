# Security and Authorization Hardening

# Purpose
Harden the entire v0.1.0 slice: implement real CSRF token generation and validation, verify server-side authorization is called on every write route, audit all repositories for prepared statements, and confirm error handlers suppress stack traces in non-local environments.

# Required Context
See `01-shared-context.md`. All prior prompts (02–09) must be complete. This prompt adds security infrastructure across existing code rather than new features.

# Required Documentation
- `docs/BarePitch-v2-02-authorization-matrix-v1.0.md` — who can do what
- `docs/BarePitch-v2-12-ai-implementation-rules-v1.0.md` — security requirements
- `docs/BarePitch-v2-03-system-architecture-v1.0.md` — CSRF, auth, and policy patterns

# Scope

## CSRF Implementation

### Token generation (`app/Http/Helpers/CsrfHelper.php`)

```php
class CsrfHelper {
    public static function getToken(): string {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validate(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
        $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        if (!$sessionToken || !hash_equals($sessionToken, $token)) {
            http_response_code(403);
            render('errors/403.php', ['message' => 'Invalid request. Please try again.']);
            exit;
        }
    }
}
```

### Integrate into front controller (`public/index.php`)

Add CSRF validation before routing:
```php
session_start(); // or use SessionHelper when available
CsrfHelper::validate(); // runs for all POST requests
```

### Add CSRF tokens to all forms

In every view with a `<form method="POST">`, add:
```html
<input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Http\Helpers\CsrfHelper::getToken()) ?>">
```

Forms to update: match creation, attendance, formation, lineup, prepare, start, goal registration, all period routes, finish.

## Authorization Audit

For each write controller method, verify the pattern is:
```php
public function store(): void {
    // 1. CSRF validated by middleware (already done)
    // 2. Policy check
    if (!MatchPolicy::canCreate()) {
        http_response_code(403);
        render('errors/403.php');
        return;
    }
    // 3. Call service
}
```

Audit and add policy checks where missing:
- `MatchController::store()` — `MatchPolicy::canCreate()`
- `MatchPreparationController::saveAttendance()` — `MatchPolicy::canPrepare()`
- `MatchPreparationController::prepare()` — `MatchPolicy::canPrepare()`
- `LiveMatchController::start()` — `LiveMatchPolicy::canStart()`
- `LiveMatchController::registerGoal()` — `LiveMatchPolicy::canRegisterEvent()`
- All period routes — `LiveMatchPolicy::canRegisterEvent()`
- `LiveMatchController::finish()` — `LiveMatchPolicy::canFinish()`

## Prepared Statement Audit

Review every Repository class. Check:
- No string interpolation in SQL: `"SELECT * FROM matches WHERE id = $id"` is a bug — replace with `prepare` + `execute`
- All user-supplied values use `?` or `:name` placeholders

If any raw interpolation is found, replace it immediately.

## Developer Login Bypass Gate

In `CurrentUser.php`:
```php
if (APP_ENV === 'local') {
    // dev bypass
}
```

Verify `APP_ENV` constant is set correctly and the bypass cannot be reached when `APP_ENV !== 'local'`.

## Output Escaping Audit

In all `.php` view files, verify every variable output uses `htmlspecialchars()`:
```php
// Correct
<?= htmlspecialchars($player['name'], ENT_QUOTES, 'UTF-8') ?>

// Wrong (XSS vulnerability)
<?= $player['name'] ?>
```

Add a helper function `e(string $value): string` that wraps `htmlspecialchars()` and use it in all views.

## Error Handler Verification

In `public/index.php`, verify the error/exception handlers:
- In `APP_ENV !== 'local'`: renders `errors/500.php`, no stack trace, no file paths
- In `APP_ENV === 'local'`: can show stack trace for debugging

# Out of Scope
- Full magic-link authentication (v0.2.0)
- Advanced rate limiting (v0.2.0)
- Role-based access for non-dev users (v0.2.0)

# Architectural Rules
- CSRF validation happens in the front controller before routing — controllers do not duplicate this check
- Policies are called inside controllers, before services
- `hash_equals()` for CSRF comparison — never `===`

# Acceptance Criteria
- Every `<form method="POST">` in the app has a `_csrf` hidden field
- Submitting any POST form with a missing or incorrect `_csrf` value returns HTTP 403
- Every write route calls the appropriate Policy method before the Service
- No SQL string interpolation in any Repository (all use prepared statements)
- All view variable output uses `htmlspecialchars()` or the `e()` helper
- Developer bypass is unreachable when `APP_ENV` is not `local`
- Production error handler shows no stack trace

# Verification
- PHP syntax check all modified files
- Remove `_csrf` from a form submission (browser dev tools) — expect 403
- Review every repository for string interpolation — must find zero occurrences
- Set `APP_ENV=production` and trigger an exception — verify generic 500 page shown

# Handoff Note
`11-testing-and-verification.md` adds the automated test suite for the entire v0.1.0 slice.

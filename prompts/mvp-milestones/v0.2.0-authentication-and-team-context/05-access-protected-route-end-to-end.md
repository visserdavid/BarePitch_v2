# Access Protected Route End to End

# Purpose
Implement request-level authentication guards, team context middleware, and CSRF middleware. Ensure all existing v0.1.0 routes are protected. Disable the temporary developer login for non-local environments.

# Required Context
See `01-shared-context.md`. Session management from `03-maintain-session-and-logout-end-to-end.md`. Team context from `04-select-team-context-end-to-end.md`.

# Required Documentation
- `docs/BarePitch-v2-02-authorization-matrix-v1.0.md` — which routes require which roles
- `docs/BarePitch-v2-03-system-architecture-v1.0.md` — middleware and layering rules
- `docs/BarePitch-v2-08-route-api-specification-v1.0.md` — public vs protected routes

# Scope

## Authentication Middleware

Create `app/Http/Middleware/AuthMiddleware.php`:
```php
public static function handle(): void {
    // Public routes: /login, /login/callback, /logout — skip auth check
    $publicRoutes = ['/login', '/login/callback', '/logout'];
    if (in_array(currentPath(), $publicRoutes, true)) return;

    if (!SessionHelper::getCurrentUserId()) {
        // Store originally requested URL in session for post-login redirect
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        redirect('/login');
        exit;
    }
}
```

## Team Context Middleware

Create `app/Http/Middleware/TeamContextMiddleware.php`:
```php
public static function handle(): void {
    // Skip for auth routes and team selection itself
    $skipRoutes = ['/login', '/login/callback', '/logout', '/teams/select', '/no-access'];
    if (in_array(currentPath(), $skipRoutes, true)) return;

    if (!SessionHelper::getCurrentUserId()) return; // AuthMiddleware handles this

    // Check no-role state
    $userRoles = $_SESSION['user_roles'] ?? [];
    if (empty($userRoles)) {
        redirect('/no-access');
        exit;
    }

    // Check active team
    if (!TeamContext::hasActiveTeam()) {
        redirect('/teams/select');
        exit;
    }
}
```

## CSRF Middleware

Add to `SessionHelper` (or a separate `CsrfHelper`):

```php
// Generate token (call once per session)
public static function getCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validate token (call in CSRF middleware for POST routes)
public static function validateCsrf(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        render('errors/403.php', ['message' => 'Invalid request token.']);
        exit;
    }
}
```

Add `<?= htmlspecialchars(SessionHelper::getCsrfToken()) ?>` as a hidden field in all forms.

## Bootstrap integration (update `public/index.php`)
Ensure middleware runs in order before routing:
1. `SessionHelper::start()`
2. `AuthMiddleware::handle()`
3. `TeamContextMiddleware::handle()`
4. `CsrfMiddleware::handle()` (validates CSRF for POST requests)
5. Router dispatches to controller

## Policy base pattern
Each Policy class must follow this pattern:
```php
class MatchPolicy {
    public static function canCreate(): bool {
        return CurrentUser::hasAnyRole(['coach', 'administrator']);
    }
    // ... other checks
}
```
Controllers call the policy before the service:
```php
public function store(): void {
    if (!MatchPolicy::canCreate()) {
        http_response_code(403);
        render('errors/403.php');
        return;
    }
    // ... call service
}
```

## Verify all v0.1.0 routes are protected
Audit the router/controller for all v0.1.0 routes:
- `POST /matches` — requires coach/admin; add Policy check if missing
- `POST /matches/{id}/attend*` — requires coach/admin
- `POST /matches/{id}/lineup` — requires coach/admin
- `POST /matches/{id}/prepare` — requires coach/admin
- `POST /matches/{id}/start` — requires coach/admin
- `POST /matches/{id}/events/*` — requires coach/admin
- `POST /matches/{id}/finish` — requires coach/admin

For each POST route: confirm CSRF is validated (via middleware) AND Policy is checked in controller.

## Disable developer bypass in non-local environments
In `CurrentUser::get()`, ensure:
```php
if (getenv('APP_ENV') === 'local' && isset($_GET['dev_login'])) {
    // dev bypass
} else {
    // real session auth
}
```
Write a test or manual check that with `APP_ENV=production`, the dev bypass query param returns the login page.

# Out of Scope
- Rate limiting (prompt 06)
- Specific policy logic for v0.3.0+ resources
- Admin-only routes (those policies live with their respective features)

# Architectural Rules
- CSRF validation happens before any controller action is invoked
- Middleware runs before routing, not inside individual controllers
- Policy is checked inside the controller, before the service is called
- `hash_equals()` must be used for CSRF comparison — not `===`

# Acceptance Criteria
- Unauthenticated `GET /players` redirects to `/login`
- Unauthenticated `POST /matches` redirects to `/login`
- POST with missing/invalid CSRF token returns HTTP 403
- Trainer attempting `POST /matches` (coach-only route) receives HTTP 403
- User with no team roles redirected to `/no-access` page
- User with team roles but no active team redirected to `/teams/select`
- Developer bypass returns login page when `APP_ENV` is not `local`
- Post-login redirect returns user to their originally requested URL

# Verification
- PHP syntax check all new/modified files
- Manually: clear session, visit `/players` — expect redirect to `/login`
- Manually: log in, submit a form without the CSRF token — expect 403
- Manually: log in as a trainer, attempt to `POST /matches` — expect 403
- Set `APP_ENV=production` in config, attempt dev bypass URL — expect login page

# Handoff Note
`06-rate-limiting.md` adds rate limiting for the login request route to prevent token flooding attacks.

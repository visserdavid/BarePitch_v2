# Authorization and CSRF Review — v0.9.0

# Purpose
Perform a route-by-route audit of every MVP route against the authorization matrix. Verify that every protected route has authentication middleware, every write route has CSRF verification, and every write controller method performs a policy check. Fix all gaps. Produce a written audit result that can be read as a record of the authorization posture at the time of the v0.9.0 milestone.

---

# Required Context
See `01-shared-context.md`. Prompts 02 through 05 must be complete. This prompt reads the route list, the authorization matrix, and the controller implementations together.

---

# Required Documentation
- `docs/BarePitch-v2-02-authorization-matrix-v1.0.md` — the authoritative list of which roles can access which routes; this is the reference against which all findings are measured
- `docs/BarePitch-v2-08-route-api-specification-v1.0.md` — complete route list with HTTP methods, auth requirements, and parameters
- `docs/BarePitch-v2-03-system-architecture-v1.0.md` — authorization belongs in Policies; middleware handles authentication
- `docs/BarePitch-v2-05-critical-behavior-specifications-v1.0.md` — public token endpoint rules; dev login gate rules

---

# Scope

## Audit Method

For each route in the application:
1. Identify the route definition in `app/routes.php` (or equivalent)
2. Identify the controller method it dispatches to
3. Answer the following questions:

| Question | Expected answer |
|---|---|
| Is this a public route? | If yes: no auth middleware required; confirm no sensitive data is exposed |
| Is this a protected route? | If yes: auth middleware must run before the controller |
| Is this a write route (POST/PUT/PATCH/DELETE)? | If yes: CSRF middleware must run; controller must call a policy |
| Which roles are allowed per the authorization matrix? | Policy must enforce exactly those roles — no more, no fewer |
| Does the controller check a policy before calling a service? | If no: add the policy check |

Record every finding in `AUTHORIZATION-AUDIT.md`.

---

## Route Inventory

Work through every route in the following categories. Add any routes present in the actual codebase that are not listed here.

### Public Routes (no authentication required)

| Route | Method | Notes |
|---|---|---|
| `/login` | GET | Render login form |
| `/login` | POST | Submit email for magic-link; CSRF required |
| `/login/callback` | GET | Consume magic-link token; no CSRF (GET); rate limited |
| `/livestream/{token}` | GET | Public livestream view; no auth; no-store headers required |

For each public route, verify:
- No session data is leaked to the view
- Error messages are generic (especially for `/login/callback` and `/livestream/{token}`)
- `/login` POST has CSRF (even though the user is not logged in — this prevents CSRF-triggered login)

### Authentication-Required Routes

For each route below, verify:
1. Authentication middleware runs and redirects to `/login` if no valid session
2. Team context is validated (user has a role in the active team)
3. Write routes have CSRF middleware
4. Write controller methods call a policy before any service call

#### Dashboard / Navigation

| Route | Method | Auth | Write | Roles |
|---|---|---|---|---|
| `/` or `/dashboard` | GET | required | no | any team role |
| `/teams/select` | GET | required | no | any authenticated user |
| `/teams/select` | POST | required | CSRF | any authenticated user |

#### Players

| Route | Method | Auth | Write | Roles per matrix |
|---|---|---|---|---|
| `/players` | GET | required | no | check matrix |
| `/players/create` | GET | required | no | check matrix |
| `/players` | POST | required | CSRF | check matrix |
| `/players/{id}/edit` | GET | required | no | check matrix |
| `/players/{id}` | POST/PUT | required | CSRF | check matrix |
| `/players/{id}/deactivate` | POST | required | CSRF | check matrix |

#### Matches

| Route | Method | Auth | Write | Roles per matrix |
|---|---|---|---|---|
| `/matches` | GET | required | no | check matrix |
| `/matches/create` | GET | required | no | check matrix |
| `/matches` | POST | required | CSRF | check matrix |
| `/matches/{id}` | GET | required | no | check matrix |
| `/matches/{id}/prepare` | GET | required | no | check matrix |
| `/matches/{id}/attendance` | POST | required | CSRF | check matrix |
| `/matches/{id}/lineup` | POST | required | CSRF | check matrix |
| `/matches/{id}/prepare` | POST | required | CSRF | check matrix |
| `/matches/{id}/start` | POST | required | CSRF | check matrix |
| `/matches/{id}/events` | POST | required | CSRF | check matrix |
| `/matches/{id}/substitutions` | POST | required | CSRF | check matrix |
| `/matches/{id}/finish` | POST | required | CSRF | check matrix |
| `/matches/{id}/corrections` | POST | required | CSRF | check matrix |
| `/matches/{id}/corrections/{corrId}` | PUT/POST | required | CSRF | check matrix |
| `/matches/{id}/corrections/{corrId}` | DELETE/POST | required | CSRF | check matrix |

#### Livestream Management (authenticated side)

| Route | Method | Auth | Write | Roles per matrix |
|---|---|---|---|---|
| `/matches/{id}/livestream` | GET | required | no | check matrix |
| `/matches/{id}/livestream/start` | POST | required | CSRF | check matrix |
| `/matches/{id}/livestream/stop` | POST | required | CSRF | check matrix |

#### Statistics

| Route | Method | Auth | Write | Roles per matrix |
|---|---|---|---|---|
| `/stats/players` | GET | required | no | any team role |
| `/stats/team` | GET | required | no | any team role |

#### Administration

| Route | Method | Auth | Write | Roles per matrix |
|---|---|---|---|---|
| `/admin/*` | GET | required | no | administrator only |
| `/admin/*` | POST | required | CSRF | administrator only |

Enumerate every admin sub-route from the route spec. The administrator role restriction applies to every `/admin/*` route.

---

## Detailed Verification Steps

### 1. Authentication Middleware Coverage

Open `app/routes.php` (or the router configuration). Verify:
- Every non-public route is inside a middleware group or has the `auth` middleware applied
- There is no route that should be protected but is accidentally registered outside the auth middleware group

```bash
grep -n "Router\|get(\|post(\|put(\|delete(" app/routes.php | head -100
```

For every route found: cross-reference with the route inventory above and confirm the middleware assignment matches.

### 2. CSRF Middleware on Write Routes

For every `POST`/`PUT`/`DELETE` route, verify the CSRF middleware is in the middleware chain. If the router groups routes by middleware, verify write routes are in the `csrf` group.

If CSRF is implemented as a middleware class:
- It must be called before the controller method
- It must return HTTP 403 on failure (not redirect, not silent failure)

### 3. Policy Call in Every Write Controller Method

Open each controller that handles a write route. For each write method, verify:
1. The method begins with a policy check:
   ```php
   if (!SomePolicy::canDoThing()) {
       http_response_code(403);
       render('errors/403.php');
       return;
   }
   ```
2. The policy check is BEFORE any input reading, validation, or service call
3. The policy is the appropriate class for the action (e.g., `MatchCorrectionPolicy::canCorrect()` for corrections, not a generic `AuthPolicy::isLoggedIn()`)

### 4. Policy Implementations Match Authorization Matrix

For each policy class in `app/Policies/`, open it and verify:
- The roles checked match the authorization matrix exactly
- The policy reads the active team role from the session (not a hardcoded role name)
- The policy does not check a superset or subset of the allowed roles

**Gap pattern to look for**:
```php
// WRONG — allows trainer when the matrix says coach/admin only:
public static function canCorrect(): bool {
    return in_array(CurrentUser::getRole(), ['coach', 'trainer', 'administrator']);
}

// CORRECT — matches the matrix:
public static function canCorrect(): bool {
    return in_array(CurrentUser::getRole(), ['coach', 'administrator']);
}
```

Confirm each policy against the matrix. Do not trust comments in the code — check the matrix document.

### 5. Resource Scoping (Insecure Direct Object Reference)

For every route with a resource ID in the URL (e.g., `/matches/{id}`, `/players/{id}`), verify:
1. The repository lookup is scoped to the active team:
   ```php
   // WRONG — any authenticated user can access any match by ID:
   $match = $this->matchRepository->findById($matchId);

   // CORRECT — scoped to the active team:
   $match = $this->matchRepository->findByIdForTeam($matchId, CurrentUser::getActiveTeamId());
   ```
2. If the resource does not belong to the active team, the response is 404 (not 403 — do not reveal the existence of another team's resource)
3. This applies to: matches, players, lineup entries, correction records, livestream tokens

### 6. Temporary Dev Login Gate

Verify the developer bypass from v0.1.0 is gated on `APP_ENV=local`. This is also covered in prompt 04 (S-11), but confirm it here as well during the authorization review:

```bash
grep -rn "APP_ENV\|dev.*login\|bypass" app/ --include="*.php"
```

### 7. Public Token Endpoint

Verify the `/livestream/{token}` endpoint:
1. Does NOT require authentication
2. DOES NOT expose any data beyond what is specified for the public livestream view
3. DOES rate limit requests (per S-08 from prompt 04)
4. DOES return generic error for invalid/expired/stopped tokens (S-10)
5. DOES send no-store and noindex headers (S-09)

---

## Audit Log File

Create `AUTHORIZATION-AUDIT.md` in the project root:

```markdown
# Authorization Audit — v0.9.0

Date: [date]

## Public Routes

| Route | Method | Auth Bypass | CSRF | Sensitive Data Check | Result |
|---|---|---|---|---|---|
| /login | GET | N/A | N/A | PASS | PASS |
| /login | POST | N/A | PASS | N/A | PASS |
| /login/callback | GET | N/A | N/A | PASS | PASS |
| /livestream/{token} | GET | N/A | N/A | PASS | PASS |

## Protected Routes

| Route | Method | Auth MW | CSRF | Policy | Roles Match Matrix | IDOR Scoped | Result |
|---|---|---|---|---|---|---|---|
| /players | GET | PASS | N/A | N/A | N/A | N/A | PASS |
| /players | POST | PASS | PASS | PASS | PASS | N/A | PASS |
| ... | ... | ... | ... | ... | ... | ... | ... |

## Findings

| # | Severity | Route | Issue | Fix Applied |
|---|---|---|---|---|
```

Every route must appear in the audit log. Any `FAIL` entry must have a corresponding `Fix Applied` entry or a GitHub issue number.

---

# Out of Scope

- OAuth or external identity provider authorization
- Attribute-based access control (ABAC) beyond what the authorization matrix specifies
- API key authentication for external integrations
- Fine-grained row-level security in the database
- Audit logging of read access (read-only routes are not logged)

---

# Architectural Rules

- Authorization (policy check) belongs in the Controller, called before any service
- Authentication (session check) belongs in Middleware, applied before the Controller
- Resource scoping belongs in the Repository, enforced by the Controller via the team ID from the session
- Policies must not query the database directly — they read from the session context
- A 404 response is returned for resources that belong to another team (obscure existence); a 403 is returned when the resource exists and belongs to the current team but the user lacks the required role

---

# Acceptance Criteria

- `AUTHORIZATION-AUDIT.md` exists and covers every route in the application
- Zero `FAIL` rows in the audit log (or every `FAIL` has a Fix Applied or a GitHub issue number)
- Every write route has CSRF middleware applied
- Every write controller method calls a policy before any service call
- Every resource lookup is scoped to the active team
- The dev login path is gated on `APP_ENV=local`
- The public livestream endpoint sends correct headers and returns generic errors

---

# Verification

1. PHP syntax check:
   ```bash
   find app/ -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"
   ```
2. Grep for controller methods with direct service calls before policy checks:
   ```bash
   grep -rn "->service\|->.*Service\|Service::" app/Http/Controllers/ --include="*.php" -B5 | grep -v "Policy::"
   ```
   Review every result and confirm a policy call precedes each service call.
3. Test unauthorized access manually:
   - Log in as a trainer; attempt `POST /matches` (coach-only); verify 403
   - Log in as a coach; attempt `GET /admin/users` (admin-only); verify 403
   - Without a session; attempt `GET /players`; verify redirect to `/login`
4. Test IDOR: log in as Team A's coach; attempt `GET /matches/{id_of_team_b_match}`; verify 404.
5. Confirm `AUTHORIZATION-AUDIT.md` is complete and has no unresolved `FAIL` entries.

---

# Handoff Note

After this prompt, the authorization posture of the application is documented and every gap is fixed. `07-documentation-alignment.md` compares the running code to the documentation and produces an alignment summary. Any authorization changes made in this prompt that differ from the spec documents must be flagged in prompt 07 for documentation update.

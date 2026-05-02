# Release-Blocking Fixes — v1.0.0

# Purpose
Address each release-blocking defect discovered during `02-pre-release-verification.md`. This prompt is only executed if the verification report produced in that step lists one or more blockers. Each fix is scoped exactly to the blocker — no feature expansion, no opportunistic refactoring, no scope creep.

---

# Required Context
See `01-shared-context.md`. The eleven release-blocking criteria are listed there. Every fix made in this prompt must correspond to at least one numbered criterion from that list.

---

# Required Documentation
Read the full documentation set listed in `01-shared-context.md` before making any fix. The fix must be consistent with the documented behavior — do not improvise a behavior that is not in the docs.

---

# Scope

## General Fix Protocol

For every blocker found in the pre-release verification report, apply the following protocol exactly:

### Protocol Steps

**1. Identify the blocker precisely.**
State the blocker by its number from `01-shared-context.md` (1–11). State the exact symptom observed during verification. Quote the relevant verification step (e.g., "Step 5.1 — Trainer `POST /matches/{id}/prepare` returned 200 instead of 403").

**2. Identify the root cause.**
Before writing any code, identify the root cause:
- Which layer is responsible? (Controller, Policy, Service, Validator, Repository, View)
- Is the cause a missing check, a wrong check, a missing guard, a missing transaction, a missing audit write, a missing recalculation, or something else?
- Does the fix require a schema change? If so, is a migration file needed?

**3. Make the minimum change required.**
Change only what is necessary to clear the blocker. Do not:
- Refactor unrelated code
- Introduce new features
- Expand test coverage beyond what verifies the fix
- Change behavior in areas not covered by the blocker

**4. Apply architectural rules.**
Every fix must respect the layer rules from `01-shared-context.md`:
- Authorization logic goes in a Policy, not a Controller or Service
- Business logic and state transitions go in a Service
- Database access goes through a Repository
- Input validation goes in a Validator
- No Controller may open a domain transaction directly

**5. Document the fix.**
After making the fix, write a fix record (see Fix Record Format below).

**6. Re-run the relevant verification step.**
After every fix, re-run the specific verification step(s) from `02-pre-release-verification.md` that failed. Confirm the step now passes. Record the re-run result.

---

## Fix Record Format

For each blocker fixed, produce a record in this format:

```
### Fix Record: Blocker #[N] — [Short description]

**Blocker criterion**: #[N] from 01-shared-context.md
**Verification step that failed**: Step [X.Y] of 02-pre-release-verification.md
**Symptom**: [Exact observed behavior]
**Root cause**: [Which layer, which file or class, what was wrong]
**Change made**:
- [File path]: [What was changed]
- [File path]: [What was changed]
**Architectural layer**: [Which layer(s) were touched]
**Migration required**: [Yes — migration file: database/migrations/NNNN_description.sql] OR [No]
**Re-verification result**: [Step X.Y re-run result — PASS or still failing (if still failing, describe)]
**Residual risk**: [Any remaining risk after this fix, or "None"]
```

---

## Blocker-Specific Guidance

The following guidance applies to the most common release-blocking defect classes. These are not exhaustive — if a blocker not covered here is found, apply the general fix protocol.

### Blocker #1 — Coach cannot complete the full match flow

Likely causes:
- A state transition guard rejects a valid state (e.g., refuses to start a `prepared` match)
- A required service method is missing or throws an unhandled exception
- A route is missing from the router

Fix approach:
- Identify exactly which step of the match flow fails
- Trace the failure from the route through Controller → Policy → Service → Repository
- Fix the specific missing or incorrect component
- Re-run Steps 4.5 through 4.14 of the acceptance scenario

### Blocker #2 — Administrator setup requires DB edits

Likely causes:
- Admin routes for club/season/phase/team/user/role assignment not fully implemented
- A required form or POST handler missing

Fix approach:
- Identify which administrative action requires a DB edit
- Implement the missing controller action, validator, policy, service, and repository method
- Re-run Step 5.4 of the authorization spot-check

### Blocker #3 — Score inconsistency

Likely causes:
- Score is stored as a raw column rather than derived from events
- Score recalculation is not called after a correction
- Score derivation query has a bug (e.g., counts own-goals for the wrong team)

Fix approach:
- Verify score derivation: it must be computed from `match_events` by the appropriate Service method
- Verify `ScoreRecalculationService::recalculate(int $matchId)` (or equivalent) is called from every path that modifies events: goal creation, goal edit, goal delete
- Fix any path where recalculation is not called
- Re-run Steps 4.7, 4.17 of the acceptance scenario, and the score consistency checks

### Blocker #4 — Lineup inconsistency

Likely causes:
- Substitution service does not atomically swap on-field and bench records
- Red-card ejection does not update the lineup record
- Lineup state is read from a stale cache or session rather than DB

Fix approach:
- Trace `SubstitutionService` to verify both the outgoing player's `lineup` record is marked off-field and the incoming player's record is marked on-field, within a single transaction
- Trace red-card ejection to verify the ejected player's `lineup` record is marked off-field
- Re-run Steps 4.8 and 4.9 of the acceptance scenario

### Blocker #5 — Red-card restrictions fail

Likely causes:
- Double-yellow → ejection logic is missing or runs at the wrong layer
- Policy check for "can this player be substituted in" does not check for prior red/double-yellow
- Sub limit check does not account for ejected players correctly

Fix approach:
- Verify that receiving a second yellow card triggers the same ejection path as a direct red card
- Verify that the substitution policy rejects any attempt to bring an ejected player back onto the field
- Re-run Step 4.9 of the acceptance scenario

### Blocker #6 — Finished-match corrections fail or are not audited

Likely causes:
- Correction routes have a state guard that blocks editing a `finished` match entirely
- Audit log write is absent from the correction service
- Lock version check is missing, causing corrections to overwrite silently

Fix approach:
- Verify correction routes are reachable for `finished` matches
- Verify `AuditLogService::write()` (or equivalent) is called from every correction path
- Verify optimistic lock version is checked before any write and incremented after
- Re-run Step 4.17 of the acceptance scenario

### Blocker #7 — Livestream fails or leaks token validity

Likely causes:
- Public livestream controller returns different status codes or messages for expired vs. non-existent tokens
- Livestream page does not send `Cache-Control: no-store`
- Token is stored plaintext

Fix approach:
- Normalize all token failure paths to return the same status code and the same generic message
- Add `header('Cache-Control: no-store')` and `header('X-Robots-Tag: noindex')` to the public livestream controller
- Verify token storage: must be `hash('sha256', $rawToken)` in the DB column
- Re-run Step 6 (token leak test) of the verification

### Blocker #8 — Authorization fails

Likely causes:
- Policy class missing for the affected route
- Controller does not call the policy (calls a hardcoded role check instead)
- Policy check uses the wrong role name or condition

Fix approach:
- Identify the specific route and role combination that produced the wrong response
- Verify the Policy class exists and its method is called by the Controller before any business logic
- Fix the policy method to match `docs/BarePitch-v2-02-authorization-matrix-v1.0.md`
- Re-run Step 5 (authorization spot-check) for the affected route/role combinations

### Blocker #9 — CSRF missing on MVP write routes

Likely causes:
- A write route was added without the CSRF middleware or CSRF token check
- The CSRF check exists but uses a hardcoded bypass that was left in place

Fix approach:
- Identify the specific route(s) missing CSRF
- Add the CSRF token check to each missing route — either via middleware or at the top of the Controller method
- Confirm the CSRF form field is present in every view that posts to these routes
- Re-run the relevant acceptance scenario sub-steps with a missing CSRF token to confirm 403 is returned

### Blocker #10 — Magic tokens are plaintext, reusable, or non-expiring

Likely causes:
- Token generated with `bin2hex(random_bytes(32))` but stored raw instead of hashed
- Token is marked used only after a grace period rather than immediately on consumption
- Token expiry check compares wrong column or uses wrong comparison direction

Fix approach:
- Verify the token generation and storage path: raw token must never be written to the DB; only `hash('sha256', $rawToken)` is written
- Verify the consumed-at or used flag is set inside the same transaction that establishes the session
- Verify the expiry comparison: `created_at + token_lifetime_seconds < NOW()`
- Re-run Step 4.1 of the acceptance scenario (re-use test)

### Blocker #11 — Public errors expose internals

Likely causes:
- `APP_ENV` check is absent from the error handler, so stack traces render in all environments
- A custom exception handler renders `$e->getMessage()` directly in the response
- A 404 or 403 handler calls `die($e)` or `var_dump`

Fix approach:
- Verify the global error/exception handler checks `APP_ENV` before rendering stack traces
- In production mode, render only a safe generic message with an appropriate HTTP status code
- Re-run Step 7 (stack trace test) of the verification

---

## Schema Changes

If a fix requires a schema change (adding a column, adding a constraint, adding a table):

1. Create a new migration file: `database/migrations/NNNN_description.sql` where `NNNN` is the next sequential number.
2. The migration must be idempotent where possible (use `IF NOT EXISTS`, `IF EXISTS`, etc.).
3. Document the schema change in the fix record.
4. Re-run Step 1 (clean schema install) of the verification on a fresh DB to confirm the migration is included in the ordered run and produces no errors.

---

# Out of Scope

- Any feature in the MVP Exclude list
- Refactoring non-blocking code
- Improving performance of non-blocking paths
- Expanding test coverage beyond what directly verifies a fix
- Adding new UI features to fix a blocker (unless the blocker is specifically that a required UI action is missing)

---

# Architectural Rules

- Every fix must respect the layer assignment rules in `01-shared-context.md`.
- A fix that moves logic to the wrong layer (e.g., putting authorization in a Service) is not acceptable even if it makes the test pass.
- Every fix that involves a write path must have: authenticate → authorize → validate → transact → recalculate derived data → fail safely.
- No fix may introduce raw SQL in a Controller.
- No fix may bypass the Policy layer.

---

# Acceptance Criteria

- Every blocker found in the pre-release verification report has a corresponding Fix Record.
- Every Fix Record includes a re-verification result of PASS.
- No new blockers were introduced by the fixes (confirm by reviewing the other verification steps after all fixes are applied).
- PHP syntax check passes after all fixes are applied.
- Automated test suite passes after all fixes are applied.
- No code was added that implements MVP Exclude scope.

---

# Verification

After all fixes are applied:

1. Re-run the PHP syntax check (Step 2 of `02-pre-release-verification.md`). Confirm zero errors.
2. Re-run the automated test suite (Step 3). Confirm zero failures.
3. Re-run each verification step that previously failed. Confirm all now pass.
4. Perform a brief review of the other verification steps (Steps 1, 4, 5, 6, 7, 8) to confirm no regression was introduced.
5. Update the pre-release verification report to reflect the new state: mark each previously failing step as now passing and append the Fix Record reference.

---

# Handoff Note

After all blockers are resolved and all re-verification steps pass, proceed to `04-release-verification-checklist.md` for the final sign-off pass. That checklist re-verifies every release criterion as a single integrated record that will serve as the human-readable release gate.

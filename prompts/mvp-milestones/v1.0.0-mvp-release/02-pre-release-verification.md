# Pre-Release Verification — v1.0.0

# Purpose
Execute a complete, systematic verification pass over the entire BarePitch codebase as it stands after v0.9.0. The output of this prompt is a written verification report. If any release-blocking criterion is found to be unmet, stop and record the exact failure before proceeding to `03-release-blocking-fixes.md`. Do not proceed to `04-release-verification-checklist.md` until all blockers found here are resolved.

---

# Required Context
See `01-shared-context.md`. All eleven release-blocking criteria are listed there. This prompt operationalizes each of them into concrete, step-by-step checks.

---

# Required Documentation
- `docs/BarePitch-v2-02-authorization-matrix-v1.0.md` — authoritative per-route role table
- `docs/BarePitch-v2-05-critical-behavior-specifications-v1.0.md` — state machine, correction rules, livestream behavior
- `docs/BarePitch-v2-10-data-integrity-test-scenarios-v1.0.md` — scenario IDs for test verification
- `docs/BarePitch-v2-06-mvp-scope-v1.0.md` — definitive include/exclude list
- `docs/BarePitch-v2-08-route-api-specification-v1.0.md` — all routes and their required auth

---

# Scope

## Step 1 — Clean Schema Install and Seed

**What to do:**
1. Drop all application tables (or use a fresh test database).
2. Run the migration files in `database/migrations/` in order. If a runner script exists in `scripts/`, use it. If not, execute each file manually in filename order.
3. Run the seed script or seed migration (if one exists). The seed must produce at minimum: one club, one season, one phase, one team, one administrator user, one coach user, enough formation definitions to create a lineup, and at least 16 players.
4. Verify the schema install completes without SQL errors.
5. Verify seed data is present: run a `SELECT` against each of the core tables (`clubs`, `seasons`, `phases`, `teams`, `users`, `user_team_role`, `players`, `formations`, `formation_positions`) and confirm at least one row per table.

**Pass criteria:** All migrations run to completion with zero errors. All seed tables contain expected data. No manual SQL intervention was needed beyond running the migration/seed scripts.

**Record in your report:** The exact migration files run, any errors encountered, and whether the install was clean.

---

## Step 2 — PHP Syntax Check

**What to do:**
Run the following command from the project root:
```bash
find app/ -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"
find public/ -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"
find database/ -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"
```

**Pass criteria:** The output of each command is empty. Any non-empty output is a blocker.

**Record in your report:** The exact command output. If zero errors, state that explicitly.

---

## Step 3 — Automated Test Suite

**What to do:**
1. Run the full PHPUnit test suite:
```bash
vendor/bin/phpunit --testdox tests/
```
2. Record the number of tests, passes, failures, and errors.
3. For any test failure or error, record: test class name, test method name, failure message.

**Pass criteria:** Zero test failures. Zero test errors. If any test is skipped, record why and confirm it is not covering a release-blocking behavior.

**Record in your report:** Full `--testdox` output, or a summary with total/passed/failed/errored counts and the full failure detail for any failing test.

---

## Step 4 — MVP Acceptance Scenario (Full Coach Flow)

Perform this scenario manually using the running local server (`php -S localhost:8000 -t public/`). Seed data from Step 1 must be present. Execute each step and record the result.

### 4.1 — Authentication
- Request a magic-link login for the coach user.
- Verify a token is generated (check the token store in DB — confirm it is stored as a hash, not plaintext).
- Visit the magic-link URL.
- Verify session is established and `session_regenerate_id` has been called (check session ID changes between pre- and post-login requests).
- Verify the raw token cannot be re-used: visit the same URL again; confirm a generic failure message is shown.

### 4.2 — Team Selection
- If coach has multiple teams, verify the team selection screen appears.
- Select the coach's team.
- Verify team context is stored in session and protected routes are now accessible.

### 4.3 — Player Management
- View the player list; confirm seeded players appear.
- Create a new player with valid data; confirm player appears in list.
- Attempt to create a player with missing required fields; confirm validation error shown, no partial record created.
- Edit an existing player's name; confirm change is visible.
- Deactivate a player; confirm player is excluded from active selection going forward.

### 4.4 — Match Creation
- Create a new match for the active season/phase/team.
- Confirm match appears in the match list in `planned` state.
- Attempt to create a match with missing required fields; confirm validation error, no partial record.

### 4.5 — Match Preparation
- Navigate to the preparation screen for the new match.
- Mark attendance for at least 14 players (present); mark 1 as injured.
- Select a formation.
- Assign 11 starters to the lineup grid and place remaining present players on bench.
- Add one internal guest player from the same club; confirm they appear in attendance.
- Attempt to add a guest from a different club (if applicable to data setup); confirm rejection.
- Click Prepare; confirm match transitions to `prepared` state.
- Attempt to re-prepare an already-`prepared` match; confirm `InvalidStateException` equivalent error.

### 4.6 — Live Match Start
- Start the match from the `prepared` state.
- Confirm match transitions to `active` and the first period begins.
- Confirm the match clock or period indicator is visible.

### 4.7 — Goal Registration
- Register a goal for the home team (scorer, assist, minute).
- Confirm the score updates immediately on the page.
- Refresh the page; confirm the score is still correct (not reverted).
- Register an own-goal; confirm it is attributed to the correct team.

### 4.8 — Substitutions
- Perform a substitution: select an on-field player and a bench player.
- Confirm the lineup reflects the change (on-field player moves to bench, bench player moves to field).
- Perform substitutions up to the documented limit.
- Attempt one substitution beyond the limit; confirm the system rejects it with an error.

### 4.9 — Cards
- Issue a yellow card to a player.
- Issue a second yellow card to the same player; confirm they are automatically ejected (red-card restriction applied).
- Confirm the ejected player cannot be selected as a substitute or put back on the field.
- Issue a direct red card to a different player; confirm immediate ejection.

### 4.10 — Penalties During Match (In-Play Penalty)
- Register a penalty event during the live match period.
- Confirm the event is recorded with the correct type and minute.

### 4.11 — Period Transitions
- End the first regular period.
- Confirm half-time state or equivalent pause.
- Start the second period.
- End the second regular period.

### 4.12 — Extra Time (if score is level)
- If the test scenario supports it, start extra time first period.
- End extra time first period; start extra time second period.
- End extra time second period.

### 4.13 — Penalty Shootout
- Start the penalty shootout.
- Register alternating kicks (scored, missed) for both teams.
- Confirm the shootout score is tracked separately from the match score.
- End the shootout; confirm a winner is determined.

### 4.14 — Match Finish
- Finish the match.
- Confirm match transitions to `finished` state.
- Confirm the basic match summary shows: final score, goal events with scorers, cards issued, substitutions, and the final lineup.
- Refresh the page; confirm all summary data persists correctly.

### 4.15 — Basic Statistics
- Navigate to the basic statistics view (player stats or team stats).
- Confirm the coach's team shows the correct record: wins/draws/losses/goals for/against.
- Confirm the players who scored in Step 4.7 show a goal count of at least 1.
- Confirm the player who was substituted off has a playing time value less than the full match duration.
- Apply a season or phase filter; confirm the filtered result changes correctly.

### 4.16 — Livestream
- Generate a livestream token for the finished (or active) match.
- Open the public livestream URL in an incognito/private window (simulating an unauthenticated public viewer).
- Confirm the livestream view loads without authentication.
- Confirm the page sends `Cache-Control: no-store` (check response headers).
- Confirm the page sends `X-Robots-Tag: noindex` (check response headers).
- Correct one event on the finished match (see Step 4.17).
- Refresh the public livestream view; confirm the correction is reflected.
- Stop the livestream token (deactivate/expire it).
- Attempt to access the public livestream URL again; confirm it returns a generic failure, not a 500 or a message revealing why the token is invalid.
- Attempt to access a livestream URL with a fabricated/random token; confirm the response is identical to the stopped-token response.

### 4.17 — Finished-Match Corrections
- Navigate to the correction screen for the finished match.
- Correct a goal event (change the minute or scorer).
- Confirm the match score or summary reflects the correction.
- Confirm an audit log record was written for this correction (check the audit log view or audit log table directly).
- Delete a goal event; confirm the score is recalculated correctly.
- Attempt to correct an event on a match the coach does not own; confirm 403.
- Attempt to correct with a fabricated lock version (optimistic locking test); confirm the system rejects the stale write.

### 4.18 — Audit Log
- Navigate to the audit log.
- Confirm the correction made in Step 4.17 appears with: actor (user), action type, affected record, timestamp.
- Confirm trainer cannot see the audit log if the authorization matrix prohibits it.

### 4.19 — Smartphone Viewport
- Resize the browser to a smartphone-sized viewport (e.g., 390px wide, or use browser devtools device mode).
- Repeat the following sub-steps at this viewport:
  - View the match preparation screen: confirm lineup grid and attendance list are usable.
  - View the live match screen: confirm event registration controls are reachable.
  - View the match summary: confirm score and events are readable.
- Record any layout or usability issues found.

**Pass criteria for Step 4:** All sub-steps complete without errors that would prevent the coach from continuing. Any UI issues found at smartphone viewport should be recorded but do not block release unless they prevent functional use.

---

## Step 5 — Authorization Matrix Spot-Check (All Four Roles)

For each of the following route/role combinations, verify the actual server response:

### 5.1 — Trainer attempting write actions
- `POST /matches/{id}/prepare` as trainer → expect 403
- `POST /matches/{id}/start` as trainer → expect 403
- `POST /matches/{id}/events` (goal registration) as trainer → expect 403
- `POST /matches/{id}/finish` as trainer → expect 403
- `POST /matches/{id}/corrections` as trainer → expect 403

### 5.2 — Team manager attempting coach-only actions
- `POST /matches/{id}/prepare` as team_manager → verify response matches authorization matrix (403 or allowed, per matrix)
- `POST /matches/{id}/events` as team_manager → verify response matches authorization matrix
- `POST /matches/{id}/corrections` as team_manager → verify response matches authorization matrix

### 5.3 — Unauthenticated user attempting protected actions
- `GET /matches/{id}` with no session → expect redirect to `/login`
- `POST /matches/{id}/prepare` with no session → expect redirect to `/login` or 401
- `GET /admin/users` with no session → expect redirect to `/login`

### 5.4 — Administrator actions
- `POST /admin/clubs` as administrator → expect success (create a club)
- `POST /admin/seasons` as administrator → expect success
- `POST /admin/teams` as administrator → expect success
- `POST /admin/users` as administrator → expect success (create a user)
- Assign a role to the new user as administrator → expect success
- Attempt the same administrator actions as a coach → expect 403

### 5.5 — Cross-team data isolation
- Log in as coach of Team A.
- Attempt to access a match belonging to Team B via direct URL (`GET /matches/{team_b_match_id}`).
- Confirm the system returns 403 or 404, not the Team B match data.
- Attempt `POST /matches/{team_b_match_id}/prepare` as coach of Team A → expect 403.

**Pass criteria:** Every check matches the expected response per the authorization matrix. Any mismatch is a release blocker.

**Record in your report:** Each check, the actual HTTP status code returned, and pass/fail.

---

## Step 6 — Public Token Endpoint Leak Test

**What to do:**
1. Generate a valid livestream token for a match.
2. Use the token (consume it or let it expire via config manipulation).
3. Request the public livestream URL with the used/expired token.
4. Request the public livestream URL with a completely fabricated token (random string of the same length).
5. Request the public livestream URL with a token that is valid format but does not exist in the DB.

**Pass criteria:** All three failure cases (used, expired, non-existent) return:
- The same HTTP status code (e.g., 200 with a generic message, or 403, or 404 — but consistent)
- The same visible error message text
- No response body difference that would allow an attacker to distinguish between the three cases
- No stack trace or internal detail in the response body

**Record in your report:** The exact response body and status code for each of the three failure cases, and whether they are distinguishable.

---

## Step 7 — Stack Trace / Error Exposure Test

**What to do:**
1. Temporarily set `APP_ENV=production` (or equivalent production error mode).
2. Trigger each of the following error conditions and observe the HTTP response:
   - Visit a non-existent route: `GET /this-route-does-not-exist`
   - Submit a form that triggers a validator exception with invalid data
   - Attempt to access a resource with an invalid integer ID: `GET /matches/not-a-number`
   - Attempt to access a resource that does not exist: `GET /matches/999999`
   - Attempt an unauthorized action: `POST /matches/{id}/finish` as trainer
3. For each response, check: does the response body contain a PHP stack trace, file path, line number, or exception class name?

**Pass criteria:** None of the above responses expose a stack trace, file path, line number, or exception class name. Each response shows a user-safe error message only.

**Record in your report:** The exact response for each triggered error condition, and whether any internal detail was exposed.

---

## Step 8 — Documentation Alignment Check

**What to do:**
For each of the following areas, compare the current implementation against the corresponding documentation. Record any discrepancy found:

1. **Route list**: compare `docs/BarePitch-v2-08-route-api-specification-v1.0.md` against the actual routes registered in the router. Are there routes in the spec that are not implemented? Are there routes implemented that are not in the spec?
2. **Authorization matrix**: compare `docs/BarePitch-v2-02-authorization-matrix-v1.0.md` against the actual Policy classes. Does every policy check match the documented permission?
3. **State machine**: compare `docs/BarePitch-v2-05-critical-behavior-specifications-v1.0.md` against the actual state transition guards in Services. Does each transition require the documented predecessor state?
4. **Schema**: compare `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md` against the installed schema. Are there columns, tables, or constraints in the docs that are missing from the schema?
5. **MVP scope**: compare `docs/BarePitch-v2-06-mvp-scope-v1.0.md` include list against the implemented modules. Is anything in the include list completely absent?

**Pass criteria:** All discrepancies are either confirmed as acceptable (e.g., a doc describes a future feature that is correctly excluded) or are triaged as potential blockers and recorded for `03-release-blocking-fixes.md`.

**Record in your report:** Each discrepancy found, its classification (blocker, non-blocker, acceptable exclusion), and the specific doc reference.

---

# Out of Scope

- Implementing new features
- Refactoring code not related to a release blocker
- Expanding test coverage beyond what is needed to verify a release criterion
- Any item in the MVP Exclude list

---

# Architectural Rules

- Do not modify any production code during this verification pass.
- If a code change is required, stop verification, record the blocker, and address it in `03-release-blocking-fixes.md`.
- Any temporary `APP_ENV` change made for error-exposure testing must be reverted before proceeding.

---

# Acceptance Criteria

- Step 1 (schema install) completed with zero SQL errors.
- Step 2 (syntax check) completed with zero PHP syntax errors.
- Step 3 (automated tests) completed with zero failures and zero errors.
- Step 4 (acceptance scenario) completed with all 19 sub-steps passing.
- Step 5 (authorization spot-check) completed with every check matching the authorization matrix.
- Step 6 (token leak test) completed with all three failure cases returning indistinguishable responses.
- Step 7 (stack trace test) completed with no internal detail exposed.
- Step 8 (documentation alignment) completed with all discrepancies classified.
- A written verification report exists covering every step.

---

# Verification

After completing all steps, produce a written **Pre-Release Verification Report** with the following sections:

```
## Pre-Release Verification Report — v1.0.0

### Step 1 — Schema Install: [PASS / FAIL]
[Details]

### Step 2 — PHP Syntax Check: [PASS / FAIL]
[Details]

### Step 3 — Automated Tests: [PASS / FAIL]
[Total / Passed / Failed / Errors]
[Failure details if any]

### Step 4 — MVP Acceptance Scenario: [PASS / FAIL]
[Sub-step results]

### Step 5 — Authorization Spot-Check: [PASS / FAIL]
[Per-check results]

### Step 6 — Token Leak Test: [PASS / FAIL]
[Response comparison]

### Step 7 — Stack Trace Test: [PASS / FAIL]
[Per-error-condition results]

### Step 8 — Documentation Alignment: [PASS / FAIL]
[Discrepancies found and classification]

### Release-Blocking Issues Found
[List each blocker by number from the release-blocking criteria in 01-shared-context.md]

### Recommendation
[Proceed to 04-release-verification-checklist.md] OR [Proceed to 03-release-blocking-fixes.md for: <list of blockers>]
```

---

# Handoff Note

If zero blockers are found: proceed directly to `04-release-verification-checklist.md`. Skip `03-release-blocking-fixes.md`.

If one or more blockers are found: proceed to `03-release-blocking-fixes.md`. After fixes are applied, return to this prompt and re-run only the steps that were failed, confirming they now pass, before proceeding to `04-release-verification-checklist.md`.

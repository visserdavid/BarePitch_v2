# Release Verification Checklist — v1.0.0

# Purpose
Final sign-off record for the v1.0.0 MVP release. Every item must be explicitly verified and checked before the `v1.0.0` tag is created. This file serves as the human-readable release gate record. Do not mark any item complete without actually verifying it. Do not create the `v1.0.0` git tag until every checkbox in this file is marked.

---

# Required Context
See `01-shared-context.md`. This checklist operationalizes the eleven release-blocking criteria and the full list of release verification tasks from that file.

---

# Required Documentation
- `docs/BarePitch-v2-02-authorization-matrix-v1.0.md`
- `docs/BarePitch-v2-05-critical-behavior-specifications-v1.0.md`
- `docs/BarePitch-v2-06-mvp-scope-v1.0.md`
- `docs/BarePitch-v2-13-solo-development-git-workflow-v1.0.md`
- `docs/BarePitch-v2-14-project-versioning-and-milestones-v1.0.md`

---

# Scope

This checklist must be completed in order. Each section corresponds to a verification task. For each item, replace `[ ]` with `[x]` only after you have personally verified it. Add a brief verification note after the checkbox where indicated.

---

## Section 1 — Schema and Environment

- [ ] **1.1** Clean schema install completes with zero SQL errors on a fresh database.
  - _Verification note_: ___
- [ ] **1.2** All migration files in `database/migrations/` run in order without errors.
  - _Verification note_: ___
- [ ] **1.3** Seed data is present after install: at minimum one club, season, phase, team, administrator user, coach user, formation, and 16+ players.
  - _Verification note_: ___
- [ ] **1.4** No manual database intervention (outside running migration/seed scripts) was required to reach a usable state.
  - _Verification note_: ___

---

## Section 2 — Code Quality

- [ ] **2.1** PHP syntax check on `app/` returns zero errors.
  - _Command run_: `find app/ -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"`
  - _Verification note_: ___
- [ ] **2.2** PHP syntax check on `public/` returns zero errors.
  - _Verification note_: ___
- [ ] **2.3** PHP syntax check on `database/` returns zero errors.
  - _Verification note_: ___

---

## Section 3 — Automated Tests

- [ ] **3.1** Full PHPUnit suite runs to completion: `vendor/bin/phpunit --testdox tests/`
  - _Total tests_: ___ | _Passed_: ___ | _Failed_: ___ | _Errors_: ___
- [ ] **3.2** Zero test failures.
  - _Verification note_: ___
- [ ] **3.3** Zero test errors.
  - _Verification note_: ___
- [ ] **3.4** Any skipped tests are recorded and confirmed not to cover release-blocking behavior.
  - _Skipped tests (if any)_: ___

---

## Section 4 — Full MVP Acceptance Scenario

- [ ] **4.1** Authentication: magic-link token generated, token stored as hash (not plaintext), session established, `session_regenerate_id` called, re-use of consumed token returns generic failure.
  - _Verification note_: ___
- [ ] **4.2** Team selection: team context stored in session, protected routes accessible after selection.
  - _Verification note_: ___
- [ ] **4.3** Player management: create player, edit player, deactivate player — all functional.
  - _Verification note_: ___
- [ ] **4.4** Match creation: match created in `planned` state, invalid submissions produce validation errors with no partial record.
  - _Verification note_: ___
- [ ] **4.5** Match preparation: attendance, formation selection, lineup assignment, internal guest player addition — all functional; prepare action transitions match to `prepared` state.
  - _Verification note_: ___
- [ ] **4.6** Live match start: match transitions from `prepared` to `active`, first period begins, clock/period indicator visible.
  - _Verification note_: ___
- [ ] **4.7** Goal registration: score updates immediately; score persists after page refresh; own-goal attributed to correct team.
  - _Verification note_: ___
- [ ] **4.8** Substitutions: lineup reflects changes; sub limit enforced; attempt beyond limit rejected with error.
  - _Verification note_: ___
- [ ] **4.9** Cards and red-card restrictions: yellow card registered; second yellow → auto-ejection; ejected player cannot re-enter or be substituted; direct red card triggers immediate ejection.
  - _Verification note_: ___
- [ ] **4.10** In-play penalty: penalty event recorded with correct type and minute.
  - _Verification note_: ___
- [ ] **4.11** Period transitions: end first period, start second period, end second period — all functional.
  - _Verification note_: ___
- [ ] **4.12** Extra time: first and second extra-time periods start and end correctly (if score allows, or confirm skipped correctly when not applicable).
  - _Verification note_: ___
- [ ] **4.13** Penalty shootout: alternating kicks recorded, shootout score tracked separately from match score, winner determined on completion.
  - _Verification note_: ___
- [ ] **4.14** Match finish: match transitions to `finished`, basic summary shows correct score/events/cards/subs/lineup, data persists on refresh.
  - _Verification note_: ___
- [ ] **4.15** Basic statistics: team record (wins/draws/losses/goals) correct; player goal count correct; substituted player has partial playing time; season/phase filter changes results correctly.
  - _Verification note_: ___
- [ ] **4.16** Livestream: public view loads without authentication; `Cache-Control: no-store` header present; `X-Robots-Tag: noindex` header present; corrections reflected on refresh; stopped token returns generic failure; fabricated token returns identical response to stopped token.
  - _Verification note_: ___
- [ ] **4.17** Finished-match corrections: event corrected on finished match; score/summary reflects correction; audit log record written; cross-team correction attempt returns 403; stale lock version rejected.
  - _Verification note_: ___
- [ ] **4.18** Audit log: correction from 4.17 visible in log with actor, action, affected record, timestamp; access restricted per authorization matrix.
  - _Verification note_: ___
- [ ] **4.19** Smartphone viewport: preparation screen, live match screen, and match summary are usable at a 390px-wide viewport.
  - _Verification note_: ___

---

## Section 5 — Authorization Matrix Spot-Check

- [ ] **5.1** `POST /matches/{id}/prepare` as trainer → returns 403.
  - _Verification note_: ___
- [ ] **5.2** `POST /matches/{id}/start` as trainer → returns 403.
  - _Verification note_: ___
- [ ] **5.3** `POST /matches/{id}/events` as trainer → returns 403.
  - _Verification note_: ___
- [ ] **5.4** `POST /matches/{id}/finish` as trainer → returns 403.
  - _Verification note_: ___
- [ ] **5.5** `POST /matches/{id}/corrections` as trainer → returns 403.
  - _Verification note_: ___
- [ ] **5.6** Team manager write actions match the authorization matrix (each action verified per matrix).
  - _Verification note_: ___
- [ ] **5.7** Unauthenticated access to `GET /matches/{id}` → redirects to `/login`.
  - _Verification note_: ___
- [ ] **5.8** Unauthenticated `POST` to a write route → redirects to `/login` or returns 401/403.
  - _Verification note_: ___
- [ ] **5.9** Administrator can create club, season, team, user, and assign role without any DB edits.
  - _Verification note_: ___
- [ ] **5.10** Coach attempting `POST /admin/users` → returns 403.
  - _Verification note_: ___
- [ ] **5.11** Cross-team isolation: coach of Team A cannot access or write to Team B's matches.
  - _Verification note_: ___

---

## Section 6 — Public Token Endpoints

- [ ] **6.1** Expired livestream token and non-existent token return the same HTTP status code.
  - _Status codes observed_: expired: ___ / non-existent: ___
- [ ] **6.2** Expired livestream token and non-existent token return the same visible message text.
  - _Verification note_: ___
- [ ] **6.3** Used/consumed token and expired token return indistinguishable responses.
  - _Verification note_: ___
- [ ] **6.4** No response body for any failure case contains a stack trace, token value, or internal identifier.
  - _Verification note_: ___
- [ ] **6.5** Public livestream success page sends `Cache-Control: no-store`.
  - _Verification note_: ___
- [ ] **6.6** Public livestream success page sends `X-Robots-Tag: noindex`.
  - _Verification note_: ___

---

## Section 7 — Production Error Handling

- [ ] **7.1** Non-existent route (`GET /this-route-does-not-exist`) returns a safe error page with no stack trace.
  - _Verification note_: ___
- [ ] **7.2** Invalid resource ID (`GET /matches/not-a-number`) returns a safe error page with no internal detail.
  - _Verification note_: ___
- [ ] **7.3** Non-existent resource (`GET /matches/999999`) returns a safe 404 page with no internal detail.
  - _Verification note_: ___
- [ ] **7.4** Unauthorized action returns a safe 403 page with no internal detail.
  - _Verification note_: ___
- [ ] **7.5** Validator rejection returns a user-safe error message with no PHP exception class or file path.
  - _Verification note_: ___
- [ ] **7.6** Tests were performed with `APP_ENV` set to production mode (or equivalent).
  - _Verification note_: ___

---

## Section 8 — Security Baseline

- [ ] **8.1** All MVP write routes have CSRF protection (verified by submitting a form without CSRF token → 403).
  - _Routes spot-checked_: ___
- [ ] **8.2** Magic-link tokens are stored as `hash('sha256', $rawToken)` — confirmed by checking the DB column directly after token generation.
  - _Verification note_: ___
- [ ] **8.3** Magic-link tokens cannot be re-used after consumption (confirmed in Step 4.1).
  - _Verification note_: ___
- [ ] **8.4** Magic-link tokens expire per the documented timeout.
  - _Verification note_: ___
- [ ] **8.5** No temporary developer login path is active when `APP_ENV` is not `local`.
  - _Verification note_: ___
- [ ] **8.6** Session settings include HttpOnly, SameSite=Lax, non-default session name, idle and absolute lifetime limits.
  - _Verification note_: ___
- [ ] **8.7** `session_regenerate_id(true)` is called on login (confirmed by observing session ID change).
  - _Verification note_: ___
- [ ] **8.8** Login endpoint is rate-limited (confirmed by checking middleware or config).
  - _Verification note_: ___
- [ ] **8.9** Public livestream endpoint is rate-limited.
  - _Verification note_: ___

---

## Section 9 — Data Consistency

- [ ] **9.1** Match score is derived from `match_events` — no independent score column that can diverge (confirmed by checking schema or Service method).
  - _Verification note_: ___
- [ ] **9.2** Shootout score is tracked separately from regulation/extra-time score.
  - _Verification note_: ___
- [ ] **9.3** After substitution, lineup table correctly reflects who is on the field and who is on the bench.
  - _Verification note_: ___
- [ ] **9.4** After red card or double yellow, ejected player's lineup record is marked off-field.
  - _Verification note_: ___
- [ ] **9.5** After a finished-match correction, score and relevant statistics are recalculated.
  - _Verification note_: ___
- [ ] **9.6** Optimistic lock version is checked before any correction write and rejected if stale.
  - _Verification note_: ___
- [ ] **9.7** Every finished-match correction produces an audit log record (confirmed in Step 4.17).
  - _Verification note_: ___

---

## Section 10 — Documentation Alignment

- [ ] **10.1** All routes in `docs/BarePitch-v2-08-route-api-specification-v1.0.md` are implemented (or confirmed as out-of-MVP-scope).
  - _Missing routes if any_: ___
- [ ] **10.2** All Policy classes match `docs/BarePitch-v2-02-authorization-matrix-v1.0.md`.
  - _Discrepancies if any_: ___
- [ ] **10.3** All state transitions match `docs/BarePitch-v2-05-critical-behavior-specifications-v1.0.md`.
  - _Discrepancies if any_: ___
- [ ] **10.4** Installed schema matches `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md` (no undocumented columns or missing documented columns).
  - _Discrepancies if any_: ___
- [ ] **10.5** All MVP include-scope features from `docs/BarePitch-v2-06-mvp-scope-v1.0.md` are implemented.
  - _Missing if any_: ___

---

## Section 11 — Release Notes

- [ ] **11.1** Release notes document exists at `docs/release-notes-v1.0.0.md` (or equivalent path).
  - _File path_: ___
- [ ] **11.2** Release notes include: scope section, included changes section, data/migration impact section, security impact section, verification summary section, known follow-up issues section.
  - _Verification note_: ___

---

## Section 12 — Final Release Gate

Complete this section last. Every checkbox above must be marked before proceeding.

- [ ] **12.1** All items in Sections 1–11 are checked.
- [ ] **12.2** Zero release-blocking criteria (from `01-shared-context.md`) remain unresolved.
- [ ] **12.3** PHP syntax check passes.
- [ ] **12.4** Automated test suite passes with zero failures.
- [ ] **12.5** Documentation and code are aligned.
- [ ] **12.6** Release notes are complete.
- [ ] **12.7** No debug shortcuts, temporary developer bypasses, or `var_dump`/`die` calls exist in production-path code.
- [ ] **12.8** The git working tree is clean (no uncommitted changes).
  - _`git status` output confirms_: ___
- [ ] **12.9** The commit to be tagged has been pushed to the remote repository.
  - _Commit SHA_: ___
- [ ] **12.10** The `v1.0.0` tag has been created: `git tag -a v1.0.0 -m "MVP release v1.0.0"`.
  - _Tag verified_: ___

---

# Out of Scope

- Making new feature changes during the checklist run
- Skipping any item on the grounds that it was "checked before"
- Marking items complete without actual verification

---

# Architectural Rules

- The checklist is not a formality — every checkbox requires an observable, verifiable action.
- If any item cannot be verified due to an implementation gap, stop and resolve the gap before continuing.
- Items in Section 12 may only be checked after all items in Sections 1–11 are checked.

---

# Acceptance Criteria

- Every checkbox in Sections 1–12 is marked.
- Every verification note is filled in (not blank and not "N/A" unless the item is explicitly inapplicable and the reason is stated).
- No release-blocking criterion remains open.
- The `v1.0.0` tag exists in the repository.

---

# Verification

This file is the verification artifact. When complete, it constitutes the release record for v1.0.0.

Retain this file in the repository under `docs/` or `prompts/` so that the release decision is permanently traceable.

---

# Handoff Note

After Section 12 is fully signed off, proceed to `05-release-notes.md` to produce the final release notes document. The release notes are written after the tag is created, based on the actual verified state of the codebase.

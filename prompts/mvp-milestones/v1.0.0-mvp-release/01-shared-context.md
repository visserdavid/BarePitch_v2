# Shared Context — v1.0.0 MVP Release

# Purpose
Reusable reference for all prompts in the v1.0.0 bundle. Read this file completely before executing any subsequent prompt in this directory. Every rule, invariant, and constraint defined here applies to every subsequent prompt unless that prompt explicitly overrides it.

---

## Milestone Identity

**Version**: v1.0.0
**Name**: MVP Release
**Goal**: Finalize and tag the first usable BarePitch MVP release. This milestone does **not** expand scope. Its only permitted activities are:
- Fixing release-blocking defects found during verification
- Resolving documentation drift (code and docs must agree before tagging)
- Resolving migration or schema installation issues
- Fixing test failures
- Closing operational gaps that would prevent normal use without developer intervention

**Critical invariant**: do not create a `v1.0.0` git tag until every release-blocking criterion listed in this file is explicitly and individually cleared.

---

## Technical Stack

- **Backend**: PHP (no framework)
- **Database**: MySQL with PDO prepared statements
- **Frontend**: Plain CSS, vanilla JavaScript
- **No**: Laravel, Symfony, Node.js, Docker, Redis, frontend frameworks, build pipeline
- **Target**: Shared hosting (Apache or Nginx, PHP 8.x)

---

## Architecture

Request flow: **Route → Controller → Validator → Policy → Service → Repository → View**

| Layer | Responsibility | Must NOT |
|---|---|---|
| Controllers (`app/Http/Controllers/`) | HTTP orchestration only | contain business rules, SQL, or authorization decisions |
| Validators (`app/Http/Requests/`) | Input shape and enum membership | check permissions or write to DB |
| Policies (`app/Policies/`) | Server-side authorization | mutate state |
| Services (`app/Services/`) | Business logic, state transitions, transactions, derived-data recalculation, audit writes | be bypassed by controllers |
| Repositories (`app/Repositories/`) | Scoped DB access via PDO | perform authorization or business transitions |
| Domain (`app/Domain/`) | Enums, value objects, constants | contain I/O |
| Views (`app/Views/`) | Render state only | make business decisions |

**Transaction rule**: only Services may open business transactions. Controllers must never open domain transactions directly.

**Dependency direction**: Controllers → Validators/Policies/Services → Repositories/Domain. No reverse dependencies. No Controller → raw SQL.

---

## MVP Include Scope

The following modules must be fully functional for `v1.0.0` to be tagged:

- Authentication (magic-link, one-time tokens, hashed storage, session security)
- Team context (role-based access, multi-team selection, safe no-access state)
- Administrator setup (club, season, phase, team, user, role assignment — no DB edits needed)
- Player management (create, edit, deactivate, status tracking)
- Guest players (internal and external guests, reuse across matches)
- Match creation (scheduled matches within a phase)
- Match preparation (attendance, formation selection, lineup grid)
- Formation and lineup grid (drag-and-drop or equivalent; bench and starters)
- Live match start and regular period flow (period transitions, clock management)
- Goal registration (scorer, assist, own-goal, penalty, period attribution)
- Substitutions (on/off pairs, bench tracking, sub limit enforcement)
- Cards and red-card restrictions (yellow, yellow-red, red; auto-ejection of double-yellow player)
- Penalties during match (penalty events distinct from shootout)
- Extra time (first and second extra-time periods)
- Penalty shootout (alternating kicks, separate shootout score, winner determination)
- Match finish (terminal state, derived score locked)
- Basic summary (score, goals, cards, subs, lineup displayed post-match)
- Livestream (token-based public view, expiry, manual stop, reflects corrections)
- Finished-match corrections (editable events on finished matches, full audit trail)
- Audit log (every finished-match correction logged; coach, administrator visible)
- Locking and concurrency (optimistic locking prevents silent overwrites)
- Basic statistics (player: match count, goals, assists, cards, playing time; team: wins, draws, losses, goals for/against; season/phase filters)
- Basic internationalization (translation helper, translation files, locale selection, fallback language, no hardcoded visible labels in MVP UI)
- Security baseline (see Security Critical Invariants below)

---

## MVP Exclude Scope

The following are explicitly **outside** v1.0.0. If any item from this list is discovered unimplemented, do not implement it — it is not a blocker. If any item from this list is found partially implemented, do not extend it.

- Advanced analytics or heatmaps
- Tactical whiteboards
- AI recommendations
- Push notifications
- Chat or social features
- External league integrations
- Automatic fixture imports
- Advanced dashboard widgets
- Parent portals
- Player photos or advanced media uploads
- Realtime websocket infrastructure
- Mobile apps
- Offline-first architecture
- Advanced exports
- Tournament systems
- Training session management
- Training attendance workflows
- Post-match ratings

---

## Release-Blocking Criteria

Any single one of these criteria blocks the `v1.0.0` tag. Do not tag until every item is individually cleared:

1. A coach cannot complete the full match flow end-to-end without developer intervention.
2. Administrator setup requires direct database edits for any normal configuration task.
3. Score can become inconsistent (derived score does not match events, or is not recalculated on correction).
4. Lineup can become inconsistent (substituted or red-carded player remains in starting position, or bench state is wrong).
5. Red-card restrictions fail (double-yellow does not eject player, red-carded player can re-enter, sub limit miscounted).
6. Finished-match corrections fail (cannot edit a finished match event) or are not audited (correction produces no audit record).
7. Livestream fails (public view unreachable, token invalid on first use) or leaks token validity details (expired/invalid responses differ in a way that reveals token existence or expiry).
8. Authorization fails for any role on any MVP route (a trainer can do what only a coach should do, or vice versa).
9. CSRF is missing on any MVP write route.
10. Magic login tokens are stored plaintext, are reusable after consumption, or do not expire.
11. Public-facing errors expose stack traces, file paths, or internal identifiers.

---

## Security Critical Invariants

These must all be true at release:

- All write routes require a valid CSRF token.
- All write routes enforce server-side authorization via a Policy.
- All database queries use PDO prepared statements.
- No stack traces, file paths, or internal exception messages are visible to end users.
- Sessions use HttpOnly, SameSite=Lax, Secure (when HTTPS), non-default session name, idle and absolute lifetime limits.
- `session_regenerate_id(true)` is called on every successful login.
- Magic-link tokens are stored as `hash('sha256', $rawToken)` — the raw token is never persisted.
- Magic-link tokens are one-time: consumed on first valid use, subsequent use returns a generic failure.
- Magic-link tokens expire per the documented timeout; expired tokens return a generic failure.
- Generic failure message is identical for expired, used, and non-existent tokens.
- Login and public livestream endpoints are rate-limited.
- Public token response pages send `Cache-Control: no-store` and `X-Robots-Tag: noindex`.
- No temporary developer login path is active outside `APP_ENV=local`.
- Production HTTPS deployments send HSTS headers.
- Invalid access attempts fail safely and do not leak resource existence.

---

## Data Consistency Critical Invariants

These must all be true at release:

- Match score is derived from the `match_events` table — never stored as a freestanding integer that can diverge.
- Shootout score is tracked separately from regulation/extra-time score.
- After any substitution, the lineup table reflects exactly who is on the field and who is on the bench.
- After a red card (or second yellow), the affected player is removed from the field and cannot re-enter.
- Playing time is stored in seconds and is recalculated consistently.
- After a finished-match correction, all derived data (score, statistics, playing time) are recalculated.
- Optimistic locking prevents concurrent writes from silently overwriting each other.
- Every finished-match correction produces an audit log record.

---

## Four Roles

The authorization matrix covers exactly these four roles. Verification must explicitly check each:

| Role | Typical capabilities |
|---|---|
| `administrator` | Club/season/phase/team/user/role setup; all match admin actions |
| `coach` | Full match flow: preparation, live match events, corrections |
| `trainer` | Read-only match view; cannot prepare, start, or modify a match |
| `team_manager` | Administrative view; limited write access per authorization matrix |

Refer to `docs/BarePitch-v2-02-authorization-matrix-v1.0.md` for the definitive per-route role table.

---

## Required Documentation

Read the complete documentation set before executing any prompt in this bundle:

- `docs/BarePitch-v2-00-documentation-map.md`
- `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md`
- `docs/BarePitch-v2-02-authorization-matrix-v1.0.md`
- `docs/BarePitch-v2-03-system-architecture-v1.0.md`
- `docs/BarePitch-v2-04-state-and-derived-data-policy-v1.0.md`
- `docs/BarePitch-v2-05-critical-behavior-specifications-v1.0.md`
- `docs/BarePitch-v2-06-mvp-scope-v1.0.md`
- `docs/BarePitch-v2-07-functional-scope-guide-v1.0.md`
- `docs/BarePitch-v2-08-route-api-specification-v1.0.md`
- `docs/BarePitch-v2-09-ui-interaction-specifications-v1.0.md`
- `docs/BarePitch-v2-10-data-integrity-test-scenarios-v1.0.md`
- `docs/BarePitch-v2-11-implementation-planning-v1.0.md`
- `docs/BarePitch-v2-12-ai-implementation-rules-v1.0.md`
- `docs/BarePitch-v2-13-solo-development-git-workflow-v1.0.md`
- `docs/BarePitch-v2-14-project-versioning-and-milestones-v1.0.md`

---

## Coding Philosophy for This Milestone

- **No invention**: do not add roles, match states, event types, token types, or behaviors not present in the docs.
- **No scope expansion**: if a missing feature is in the MVP Exclude list, leave it out. If a missing feature is in the MVP Include list and it is also a release blocker, fix only what is needed to unblock.
- **Fix then re-verify**: after fixing any blocker, re-run the specific verification steps that would have caught it. Do not assume the fix is correct.
- **Docs and code must agree**: if a discrepancy is found between docs and implementation, resolve it explicitly — update the doc or update the code, but do not leave them diverged.
- **When docs conflict**: stop. Do not interpret ad hoc. Update the docs using the precedence order in `docs/BarePitch-v2-00-documentation-map.md`.

---

## Execution Order

Execute prompts in this directory in order:

1. `01-shared-context.md` — this file (read first, do not execute)
2. `02-pre-release-verification.md` — systematic verification; produces a written report
3. `03-release-blocking-fixes.md` — fix only the blockers found in step 2
4. `04-release-verification-checklist.md` — final sign-off checklist; each item must be explicitly ticked
5. `05-release-notes.md` — produce the v1.0.0 release notes document

---

## Handoff Note

This is the terminal milestone. After `04-release-verification-checklist.md` is fully signed off and all release-blocking criteria are cleared, and after `05-release-notes.md` is completed, the release may be tagged `v1.0.0`. Do not tag early.

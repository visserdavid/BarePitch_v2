# Shared Context — v0.9.0 Hardening and MVP Candidate

# Purpose
Reusable reference for all prompts in the v0.9.0 bundle. Read this file completely before executing any subsequent prompt in this directory. Every sub-prompt assumes this context is active.

---

## Milestone Identity

**Version**: v0.9.0
**Name**: Hardening and MVP Candidate
**Goal**: Close security, consistency, authorization, test, UI, and documentation gaps accumulated across v0.1.0 through v0.8.0. Prepare the codebase for MVP release. This is NOT a feature-expansion milestone — no new user-facing flows are added beyond minimal statistics and i18n foundation.

**This milestone is a discipline gate.** Every item in the security checklist and data consistency checklist must pass before work is considered done. The output of this milestone is the codebase in a state that could be released as v1.0.0 with only final validation work remaining.

---

## Technical Stack

- **Backend**: PHP (no framework)
- **Database**: MySQL with PDO prepared statements
- **Frontend**: Plain CSS, vanilla JavaScript
- **No**: Laravel, Symfony, Node.js, Docker, Redis, frontend frameworks, build pipeline
- **Target**: Shared hosting (Apache or Nginx, single server, no container orchestration)

---

## Architecture

Request flow: **Route → Controller → Validator → Policy → Service → Repository → View**

| Layer | Responsibility | Must NOT |
|---|---|---|
| Controllers (`app/Http/Controllers/`) | HTTP orchestration only | contain business rules, SQL, or auth decisions |
| Validators (`app/Http/Requests/`) | Input shape and enum membership | check permissions or write to DB |
| Policies (`app/Policies/`) | Server-side authorization | mutate state |
| Services (`app/Services/`) | Business logic, state transitions, transactions, derived-data recalculation, audit writes | be bypassed by controllers |
| Repositories (`app/Repositories/`) | Scoped DB access via PDO | perform authorization or business transitions |
| Domain (`app/Domain/`) | Enums, value objects, constants | contain I/O |
| Views (`app/Views/`) | Render state only | make business decisions |

**Transaction rule**: Only Services may open business transactions. Controllers must never open domain transactions directly.

**Dependency direction**: Controllers → Validators/Policies/Services → Repositories/Domain. No reverse dependencies. No Controller → raw SQL.

---

## What Prior Milestones Established

| Milestone | What it delivered |
|---|---|
| v0.1.0 | Project skeleton, autoloader, router, PDO, error handler, full schema, seed data, temporary dev login |
| v0.2.0 | Magic-link authentication, secure sessions, team context, route protection middleware, CSRF middleware, rate limiting on login |
| v0.3.0 | Player management (list/create/edit/deactivate), external guests, full match creation flow |
| v0.4.0 | Match preparation: attendance, guest selection, lineup grid, prepare state transition with server-side validation |
| v0.5.0 | Live match core: start match, register goals/assists, match clock, finish match |
| v0.6.0 | Substitutions and cards: substitution rules, yellow/red card restrictions, lineup current-state enforcement |
| v0.7.0 | Extra time, penalties, and penalty shootout: shootout score separate from normal score |
| v0.8.0 | Livestream share tokens, finished-match corrections, correction audit log, locking |

This milestone audits and hardens everything delivered in v0.1.0 through v0.8.0.

---

## Security Checklist

Every item must pass before this milestone is marked complete. If an item cannot be fixed within this milestone's scope, it must be documented as a tracked issue with a blocking label.

| # | Item | Expected State |
|---|---|---|
| S-01 | All write routes require CSRF | Every `POST`/`PUT`/`DELETE` route validates `_csrf` token server-side |
| S-02 | All write routes check policy server-side | Policy called in controller before any service call |
| S-03 | SQL uses prepared statements | No string interpolation in SQL queries |
| S-04 | No stack traces visible to users | `display_errors=0` in production; generic error page shown |
| S-05 | Sessions use secure settings | HttpOnly, SameSite=Lax, Secure (HTTPS), non-default session name, idle/absolute lifetime enforced |
| S-06 | Magic links are one-time and expire | `used_at` set on first consume; expired tokens rejected; both return generic failure |
| S-07 | Magic-link tokens stored hashed | SHA-256 hash stored; raw token never persisted |
| S-08 | Login and public livestream endpoints rate-limited | Configurable threshold; excess requests rejected with 429 |
| S-09 | Public token pages send no-store and noindex | `Cache-Control: no-store`, `X-Robots-Tag: noindex` on public livestream pages |
| S-10 | Public token failures use generic messaging | Expired, used, and invalid tokens return identical generic message |
| S-11 | No dev login active outside local | Dev bypass gated on `APP_ENV === 'local'`; unreachable in staging/production |
| S-12 | HSTS sent on production HTTPS | `Strict-Transport-Security` header sent when `APP_ENV=production` and HTTPS |
| S-13 | Session idle and absolute lifetime enforced | Idle: per docs spec; Absolute: per docs spec; both checked server-side |
| S-14 | Invalid access attempts fail safely | Unauthorized access returns 403/redirect with no information leak |

---

## Data Consistency Checklist

Every item must pass before this milestone is marked complete.

| # | Item | Expected State |
|---|---|---|
| D-01 | Score is source-derived from events | Match score is never manually set; always calculated from goal events in `match_events` |
| D-02 | Shootout score separate from normal score | Penalty shootout goals stored in separate column/table; never added to normal score |
| D-03 | Lineup current state valid after substitutions and red cards | Substituted-off and red-carded players removed from active lineup; substitutes added correctly |
| D-04 | Playing time stored in seconds and consistent | `playing_time_seconds` incremented correctly; substitute entry and exit times used |
| D-05 | Finished-match corrections recalculate derived data | Calling any correction always triggers full recalculation of score and statistics |
| D-06 | Locking prevents silent overwrite | Optimistic lock version checked before every correction write; conflict returns safe error |
| D-07 | Audit log never skipped | Every finished-match correction writes to audit log in same transaction; no path bypasses this |

---

## MVP Scope Discipline

The following are explicitly OUT OF SCOPE for this milestone and must not be built:

- Advanced analytics or aggregate dashboards beyond basic team/player statistics
- Player ratings, performance scoring, or any subjective evaluation feature
- Training management or training session tracking
- Expanded real-time dashboard with websocket or polling infrastructure
- Social features, comments, or notifications
- Any new authentication method or identity provider
- Calendar integrations or scheduling beyond existing match creation

If a gap is discovered that would require adding one of the above, document it as a post-MVP issue and proceed without implementing it.

---

## Global Exclusions for This Milestone

- New user-facing flows not listed in the sub-prompts
- New database tables not required by statistics or i18n
- Changes to the domain schema (any schema change requires a migration file and docs update)
- Removal of any existing MVP feature
- Any feature marked "full-scope" in `docs/BarePitch-v2-06-mvp-scope-v1.0.md` that has not already been scaffolded

---

## Coding Philosophy

- **No invention**: do not add roles, token types, session keys, match states, statistics columns, or security behaviors not documented in the spec. If a required detail is missing from docs, stop and surface the gap before proceeding.
- **Every write path must**: authenticate → authorize → validate input shape → validate text lengths → validate domain state → transact → recalculate derived data → fail safely without partial state.
- **Feature is not complete until**: behavior implemented, authorization enforced, validation enforced, free-text limits enforced, errors fail safely, derived state correct, tests exist, docs updated if behavior changed.
- **Security**: prepared statements everywhere, CSRF on all state-changing routes, hashed token storage, server-side authorization. UI hiding is never sufficient.
- **When docs conflict**: stop — do not silently compromise. Update the docs or surface the conflict. Never change behavior to resolve a conflict without flagging the change.

---

## Required Documentation

Read all of the following before implementing any work in this bundle:

- `docs/BarePitch-v2-00-documentation-map.md` — precedence rules; read first
- `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md` — canonical schema
- `docs/BarePitch-v2-02-authorization-matrix-v1.0.md` — role permissions per route
- `docs/BarePitch-v2-03-system-architecture-v1.0.md` — layer rules, dependency direction
- `docs/BarePitch-v2-04-state-and-derived-data-policy-v1.0.md` — state machine, derived data recalculation
- `docs/BarePitch-v2-05-critical-behavior-specifications-v1.0.md` — correction, audit, locking, livestream rules
- `docs/BarePitch-v2-06-mvp-scope-v1.0.md` — MVP vs. full-scope feature boundary
- `docs/BarePitch-v2-07-functional-scope-guide-v1.0.md` — what is in and out of MVP
- `docs/BarePitch-v2-08-route-api-specification-v1.0.md` — every route, method, auth requirement
- `docs/BarePitch-v2-09-ui-interaction-specifications-v1.0.md` — UI behavior rules
- `docs/BarePitch-v2-10-data-integrity-test-scenarios-v1.0.md` — test scenario identifiers
- `docs/BarePitch-v2-11-implementation-planning-v1.0.md` — phase structure
- `docs/BarePitch-v2-12-ai-implementation-rules-v1.0.md` — AI implementation constraints
- `docs/BarePitch-v2-13-solo-development-git-workflow-v1.0.md` — git workflow
- `docs/BarePitch-v2-14-project-versioning-and-milestones-v1.0.md` — versioning rules
- `docs/BarePitch-v2-15-operational-and-implementation-details-v1.0.md` — hosting, config, error handling

---

## Execution Order

Sub-prompts in this bundle should be executed in this order:

1. `01-shared-context.md` — this file (read only)
2. `02-basic-statistics.md` — implement statistics layer
3. `03-internationalization-foundation.md` — implement i18n helper and replace hardcoded labels
4. `04-security-hardening-review.md` — systematic security audit and fixes
5. `05-data-consistency-review.md` — verify and fix derived data correctness
6. `06-authorization-and-csrf-review.md` — route-by-route authorization and CSRF audit
7. `07-documentation-alignment.md` — compare code to docs; fix docs if code is correct
8. `08-testing-and-verification.md` — complete test suite and manual walk-through

Prompts 04 through 06 may surface issues that require fixes in earlier layers. If that happens, fix the issue in the appropriate layer file, then continue with the review. Do not defer fixes discovered during a review prompt.

---

## Handoff Note

This bundle is the final hardening pass before v1.0.0. When all 8 prompts are complete and all checklists pass, the codebase is the MVP candidate. Do not tag or label this as `v1.0.0`; that is the job of `prompts/mvp-milestones/v1.0.0-mvp-release.md`.

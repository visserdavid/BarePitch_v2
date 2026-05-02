# Shared Context — v0.8.0 Livestream, Corrections, and Audit

# Purpose
Reusable reference for all prompts in the v0.8.0 bundle. Read this before executing any subsequent prompt in this directory.

---

## Milestone Identity

**Version**: v0.8.0
**Name**: Livestream, Finished Match Corrections, and Audit
**Goal**: Provide public match viewing without login and allow authorized post-match corrections with complete audit logging. Covers Phases 11 and 12 of the implementation plan.

---

## Technical Stack

- **Backend**: PHP (no framework)
- **Database**: MySQL with PDO prepared statements
- **Frontend**: Plain CSS, vanilla JavaScript (polling, lock refresh — server is authoritative)
- **No**: Laravel, Symfony, Node.js, Docker, Redis, frontend frameworks, build pipeline
- **Deployment target**: Shared hosting with PHP and MySQL

---

## Architecture

Request flow: **Route → Controller → Validator → Policy → Service → Repository → View**

| Layer | Rule |
|---|---|
| Controllers | HTTP orchestration only; no business rules, no SQL |
| Validators (`app/Http/Requests/`) | Input shape and enum membership; no permissions, no DB writes |
| Policies (`app/Policies/`) | Server-side authorization; no mutation |
| Services (`app/Services/`) | Own all business logic, state transitions, transactions, derived-data recalculation, audit writes |
| Repositories (`app/Repositories/`) | Scoped DB access; no authorization, no business transitions |
| Domain (`app/Domain/`) | Enums, value objects, constants |
| Views (`app/Views/`) | Render state only; no business decisions |

**Transaction rule**: Only Services may open business transactions. Controllers must never open domain transactions directly.

**Dependency direction**: Controllers → Validators/Policies/Services → Repositories/Domain. No reverse dependencies. No Controller → raw SQL.

---

## What Prior Milestones Established

- **v0.1.0**: Project skeleton, schema migration, seed data, match creation, minimal preparation stub, match summary view
- **v0.2.0**: Magic-link authentication, secure sessions, team context switching, CSRF middleware, route protection
- **v0.3.0**: Player management (list/create/edit/deactivate), external guest players, full match creation flow in planned state
- **v0.4.0**: Match preparation (attendance, guest selection, formation, lineup grid, planned→prepared state transition with all validation rules)
- **v0.5.0**: Live match core (prepared→active transition, regular period control, goal/penalty registration, score recalculation, match finish)
- **v0.6.0**: Substitutions, yellow/red cards, lineup state updates, playing time tracking
- **v0.7.0**: Extra time, penalty shootout (attempts, separate shootout score, manual and automatic ending)

This milestone adds the public livestream surface, finished-match correction flow, match edit locking for corrections, score recalculation after correction, audit logging, and testing for all of the above.

---

## Critical Invariants

### Token Storage

- Livestream tokens must **never** be stored in plaintext
- Token generation must use cryptographically secure entropy (e.g., `random_bytes(32)`)
- Store only `hash('sha256', $rawToken)` in `livestream_token.token_hash` — `CHAR(64)` column for SHA-256 hex
- The raw token is delivered once to the coach via the public URL and never re-read from the database
- Old tokens must immediately stop granting access when rotated or stopped

### Livestream Lifecycle

- Livestream starts automatically when match transitions `prepared → active`
- Token is generated as part of the match-start transaction in `LiveMatchService`
- `livestream_token.issued_at` is set at token creation
- `livestream_token.expires_at` = `match.finished_at + team.livestream_hours_after_match` — set in the same transaction as match finish
- Default configured duration: 24 hours; maximum configured limit: 72 hours
- Only one active token per match grants public access at a time
- Coach may stop livestream early by setting `livestream_token.stopped_at`
- Expired or stopped tokens deny access with a generic failure page — no detail about whether the token existed
- Token rotation: old token marked invalid, new token generated and stored hashed; requires recent authentication per authorization matrix

### Public Livestream Access

- `GET /live/{token}` and `GET /live/{token}/data` are public (no session required)
- Both routes must send `Cache-Control: no-store`, `Referrer-Policy: no-referrer`, `X-Robots-Tag: noindex, nofollow`
- Both routes must be rate-limited or prepared for rate limiting (at minimum, the application architecture must not prevent it)
- Generic failure on invalid/expired/stopped token — do not reveal whether a token ever existed
- Livestream content must not expose private notes, attendance data, ratings, or internal administrative data

### Corrections

- Only coach and administrator may correct finished matches — trainer and team_manager may not
- Match status remains `finished` after every correction — no status change ever happens during correction
- Every correction must: acquire lock → validate → start transaction → update source record → recalculate affected derived/cached data → write audit log → commit
- No partial correction: if any step fails, rollback and leave all data unchanged
- Score is never edited directly — it can only change through source-event correction followed by `ScoreRecalculationService`
- Corrections visible while livestream is active: the next poll must return corrected data

### Audit Logging

- `AuditService::log()` is called inside every correction transaction — never after commit, never skipped
- If `AuditService::log()` throws, the entire transaction rolls back — the correction is only complete when the audit entry is persisted
- Audit record fields: `entity_type`, `entity_id`, `match_id`, `user_id`, `action_key`, `field_name`, `old_value_json`, `new_value_json`, `created_at`
- `audit_log` is append-only; no updates or deletes in normal product operation

### Match Edit Locking

- One active editor per match at any time — applies to live control and corrections
- Lock is stored in `match_lock` table: `match_id`, `user_id`, `locked_at`, `expires_at`
- Recommended timeout: 2 minutes (`expires_at = NOW() + 120 seconds`)
- Lock acquisition logic:
  1. Check for existing lock
  2. If no lock: acquire
  3. If expired (`expires_at < NOW()`): replace with new lock
  4. If owned by same user: refresh (extend `expires_at`)
  5. If held by another user and not expired: deny with `locked` error
- Lock refresh must verify the requesting user still has edit permission for the match
- No silent overwrite: when a second user is blocked, return a clear error, never proceed silently
- Lock ownership does not elevate privileges — authorization must be checked independently

### Score Recalculation Rule

- Score is **never** updated by blind increment or decrement
- `ScoreRecalculationService` recounts `goals_scored` and `goals_conceded` from `match_event` (event_type `goal` or `penalty` with outcome `scored`, filtered by `team_side`)
- Shootout score is recounted separately from `penalty_shootout_attempt`
- Called at the end of every correction transaction that affects a scoring event or shootout attempt

---

## Coding Philosophy

- No silent invention of new roles, match states, token types, or enum values
- Every write path: authenticate → authorize → validate input shape → validate domain state → transact → recalculate derived data → write audit → fail safely
- Feature is not complete until: behavior implemented, authorization enforced, validation enforced, free-text limits enforced, errors fail safely, derived state correct, tests exist

---

## Global Exclusions for This Milestone

- Player ratings (post-MVP)
- Training module enhancements
- Advanced statistics dashboard
- Extended multi-language support beyond what prior milestones established
- Any automatic match-status transition (match status never changes during correction)

---

## Required Documentation

- `docs/BarePitch-v2-00-documentation-map.md`
- `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md` — `livestream_token`, `match_lock`, `audit_log`, `match_event`, `penalty_shootout_attempt`, `substitution` schemas
- `docs/BarePitch-v2-02-authorization-matrix-v1.0.md` — livestream public view, stop, rotate token, finished correction rows
- `docs/BarePitch-v2-03-system-architecture-v1.0.md` — service ownership, transaction rules, dependency direction
- `docs/BarePitch-v2-04-state-and-derived-data-policy-v1.0.md` — recalculation triggers, cached fields, livestream projection policy
- `docs/BarePitch-v2-05-critical-behavior-specifications-v1.0.md` — sections 14, 15, 16 (livestream, corrections, locking)
- `docs/BarePitch-v2-06-mvp-scope-v1.0.md`
- `docs/BarePitch-v2-07-functional-scope-guide-v1.0.md`
- `docs/BarePitch-v2-08-route-api-specification-v1.0.md` — sections 20, 21, 24 (livestream routes, correction routes, lock routes)
- `docs/BarePitch-v2-09-ui-interaction-specifications-v1.0.md`
- `docs/BarePitch-v2-10-data-integrity-test-scenarios-v1.0.md` — sections 14, 15, 16 (LS, AU, LK scenarios), SC-06
- `docs/BarePitch-v2-11-implementation-planning-v1.0.md`
- `docs/BarePitch-v2-12-ai-implementation-rules-v1.0.md`

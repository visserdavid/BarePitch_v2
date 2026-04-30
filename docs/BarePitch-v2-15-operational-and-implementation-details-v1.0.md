# BarePitch — Operational and Implementation Details
Version 1.0 — April 2026

---

## 1. Purpose

This document defines practical implementation and operational policies for BarePitch.

It covers details that materially affect:
- correctness
- reliability
- security
- deployment
- maintenance
- recoverability

This document exists because some important decisions are not product features, route contracts, or domain entities, but still need to be consistent across implementation.

---

## 2. Scope

This document covers:
- validation conventions
- date, time, and timezone policy
- environment configuration
- migrations
- seed data
- backups
- error logging
- audit retention
- deployment assumptions
- operational safeguards
- stale form behavior
- repair and recalculation operations

This document does not override:
- domain entities and schema definitions
- authorization rules
- architecture boundaries
- source-of-truth and derived-data rules
- critical match behavior
- route contracts
- UI interaction rules
- test scenarios

When a policy in this document depends on one of those areas, the more specific source document wins.

---

## 3. Documentation Precedence

This document sits after the AI implementation rules and before the solo Git workflow in the project documentation precedence.

Use this document for:
- deployment policy
- migration policy
- seed data policy
- backup policy
- logging policy
- environment policy
- operational safeguards

Do not use this document to redefine:
- schema shape
- role permissions
- match state transitions
- route paths
- UI behavior

If operational policy conflicts with a higher-precedence document, update the relevant higher-precedence document first or request clarification.

---

## 4. Data and Validation Policy

All user input must be validated server-side.

Frontend validation is a usability aid only.

### 4.1 Enum Handling

Status-like fields must use documented enum values.

Implementation must not silently invent new enum values.

Examples:
- match status
- active phase
- attendance status
- guest type
- event type
- event outcome
- role key

Unknown enum values must be rejected before persistence.

### 4.2 Required vs Nullable Fields

Every persisted field must be intentionally treated as either:
- required
- optional and nullable
- optional but stored with a documented default

Optional free-text fields should normalize empty strings to `NULL`.

Required text fields must reject empty or whitespace-only values.

### 4.3 Free-Text Lengths

Free-text length limits are defined in `BarePitch-v2-01-domain-model-and-schema-v1.0.md`.

Implementation must enforce them in:
- HTML form attributes where applicable
- request validators
- database column definitions or equivalent constraints

Silent truncation is not allowed.

If a submitted value exceeds the documented limit:
- reject the request with a validation error
- preserve safe submitted input for correction
- do not partially store the value

### 4.4 Output Escaping

All user-provided text must be escaped when rendered.

This includes:
- player names
- team names
- opponent names
- notes
- phase labels
- formation labels
- livestream timeline text

Escaping must happen at render time even if input validation already ran.

---

## 5. Date, Time, and Timezone Policy

BarePitch must handle dates and times consistently.

### 5.1 Storage

Timestamps should be stored in UTC.

Examples:
- `created_at`
- `updated_at`
- `started_at`
- `ended_at`
- `finished_at`
- `expires_at`
- `locked_at`
- `used_at`

Date-only fields may remain date-only.

Examples:
- season start date
- season end date
- match date

### 5.2 Display

Dates and times should display in the user's locale where available.

If user locale is unavailable, use the active team or application fallback locale.

The application must not mix UTC storage values directly into user-facing labels without formatting.

### 5.3 Match Timing

Live match event timing must use explicit match timing fields.

Event timing should include:
- `period_id`
- `match_second`
- `minute_display`

`match_second` is the calculation-friendly value.

`minute_display` is the coach-facing representation.

The system must not derive event order only from wall-clock timestamps.

### 5.4 Daylight Saving Time

Date and time display must tolerate daylight-saving changes.

Match event timing is relative to the match and period, so daylight-saving changes must not alter match event order or playing-time calculations.

---

## 6. Environment Configuration

Configuration must be explicit per environment.

Required environment categories:
- local
- staging
- production

### 6.1 Required Configuration

The application should define configuration for:
- database host
- database name
- database user
- database password
- application environment
- application base URL
- mail sender
- mail transport
- session security settings
- HTTPS/HSTS behavior
- login token lifetime
- session idle lifetime
- session absolute lifetime
- rate-limit settings
- error display setting
- error log location

### 6.2 Secret Handling

Secrets must not be committed to the repository.

Examples:
- database passwords
- mail credentials
- application secrets
- token signing or encryption keys if introduced

Local example configuration may exist, but real secret values must not be stored in version control.

### 6.3 Developer Login Safeguard

Temporary developer login is allowed only for local development when needed.

It must be disabled by configuration in:
- staging
- production

If the application cannot prove the environment is local, developer login must be unavailable.

---

## 7. Database Migrations

Schema changes must be reproducible.

### 7.1 Migration Principles

Migrations should be:
- ordered
- repeatable in a clean environment
- safe to run once
- reviewed before production execution

Migration filenames should sort chronologically.

Recommended format:
- `YYYYMMDDHHMM_description.sql`

### 7.2 Rollback Policy

The default policy is forward-only migrations.

For high-risk migrations, provide a documented rollback or repair plan.

Rollback plans may be manual if the project is still pre-MVP, but production releases must document data impact.

### 7.3 Production Migration Safety

Before production migration:
- create database backup
- confirm target environment
- run syntax check where possible
- verify affected tables
- record migration result

Production migrations must not rely on manual undocumented database edits as normal workflow.

---

## 8. Seed Data

Seed data exists to make development and verification reliable.

### 8.1 Local Seed Data

Local seed data may include:
- one club
- one active season
- one phase
- one team
- administrator user
- coach role
- trainer role
- team manager role
- formation
- sample players
- sample match where useful

Local seed data must not contain real private data.

### 8.2 Test Seed Data

Test seed data should be deterministic.

Automated tests must not depend on fragile manual seed state unless explicitly documented.

### 8.3 Production Setup Data

Production should start with only the minimum required setup.

Recommended minimum:
- first administrator account
- first club
- first season
- first phase
- first team
- first role assignments

Production seed data must not create demo users, demo matches, or sample players unless intentionally running a demo environment.

---

## 9. Backup and Recovery

BarePitch must assume the database is the primary system of record.

### 9.1 Backup Expectations

Production deployments should have database backups.

Recommended minimum:
- daily automated database backup
- backup before every production migration
- retained recent backups long enough to recover from accidental changes

The exact retention period may depend on hosting constraints, but it must be documented for each production deployment.

### 9.2 Restore Testing

A backup policy is incomplete unless restore has been tested.

Before MVP release, at least one restore test should be performed in a non-production environment.

### 9.3 Recoverability

The following must be recoverable from backup:
- users
- teams
- players
- matches
- match events
- substitutions
- shootout attempts
- audit logs
- livestream token metadata

Public/login token plaintext must not be recoverable because it must never be stored.

### 9.4 Manual Recovery Policy

Manual database edits are not normal product behavior.

If manual recovery is required:
- document the cause
- document the exact change
- create a follow-up issue
- prefer product or migration fixes over repeated manual edits

---

## 10. Error Handling and Logging

User-facing errors must be calm and non-technical.

Internal logs must provide enough detail to diagnose failures.

### 10.1 Production Error Behavior

Production must not display:
- stack traces
- SQL errors
- file paths
- secrets
- token values
- raw exception details

Production should show safe generic errors.

### 10.2 Internal Logging

Internal logs may include:
- timestamp
- route
- authenticated user id where available
- team or match id where relevant
- error category
- exception class
- safe message
- correlation/request id if implemented

Internal logs must not include:
- plaintext login tokens
- plaintext livestream tokens
- session ids
- passwords or secrets
- full sensitive request payloads

### 10.3 Public Token Endpoint Logging

Public token failures may be logged internally, but user-facing responses must remain generic.

Logs should avoid storing the submitted raw token.

If token correlation is needed, log a hash prefix or internal token record id after lookup.

---

## 11. Audit Log Governance

Audit logging is distinct from technical error logging.

Audit logs record meaningful user actions and sensitive changes.

### 11.1 Read Access

Audit logs should be readable only by authorized administrative users unless a more restrictive policy is introduced.

Normal coaches, trainers, and team managers should not receive broad audit-log access by default.

### 11.2 Retention

Audit logs should be retained at least through the active season and a reasonable post-season review period.

Exact retention can be deployment-specific, but audit logs must not be silently deleted by normal product workflows.

### 11.3 Append-Only Rule

Audit records are append-only in normal application operation.

Corrections to audit mistakes should be handled by additional audit entries or administrative repair procedures, not silent edits.

### 11.4 Sensitive Values

Audit logs must not store plaintext tokens or secrets.

For changed user text, audit logs may store old and new values when needed for correction traceability.

---

## 12. Security Operations

Security requirements must remain active in production, not just documented.

### 12.1 HTTPS and HSTS

Production should run over HTTPS.

Production HTTPS deployments should send HSTS.

Local development may run without HTTPS.

### 12.2 Session Lifetimes

Session policy defaults:
- idle timeout: 30 minutes
- absolute lifetime: 12 hours
- regenerate session after login
- invalidate session server-side on logout

If deployment constraints require changes, document them.

### 12.3 Rate Limiting

Rate limiting must protect:
- login requests
- magic-link token consumption
- public livestream endpoints

Rate limits may use database-backed storage for shared-hosting compatibility.

Rate-limit failures must not reveal sensitive state.

### 12.4 Token Rotation

Livestream tokens should support invalidation or rotation if exposure is suspected.

Old livestream tokens must stop granting access after rotation.

---

## 13. Concurrency and Stale Form Policy

BarePitch must prevent silent overwrite.

### 13.1 Lock Expiry

Recommended match lock timeout:
- 2 minutes

Recommended refresh interval:
- 30 seconds

An expired lock may be acquired by another authorized user.

### 13.2 Stale Form Submission

If a user submits a form after losing a lock:
- reject the write
- keep submitted values where safe
- show a clear conflict message
- require the user to refresh or reacquire the lock

The application must not silently apply stale writes over newer state.

### 13.3 Conflict Messaging

Conflict messages should be understandable.

Example:
- This match is being edited by another user. Refresh before making more changes.

Avoid exposing unnecessary user details unless the authenticated UI intentionally shows who holds the lock.

---

## 14. Statistics and Derived Data Operations

Derived data must remain repairable.

### 14.1 Recalculation Triggers

Recalculation must happen when source data changes.

Examples:
- match event created or corrected
- penalty shootout attempt created or corrected
- substitution corrected
- red card registered
- training attendance changed
- rating completed or edited

### 14.2 Repair Operations

The system should eventually provide repair or rebuild operations for derived data.

Repair operations may be command-line, admin-only, or migration-based depending on deployment constraints.

Repair operations must:
- read from canonical source tables
- update derived fields consistently
- report what changed
- avoid destructive cleanup unless explicitly approved

### 14.3 Stale-State Detection

Where practical, tests or admin checks should detect:
- score cache mismatch
- shootout score mismatch
- lineup state inconsistency
- playing-time inconsistency
- rating completion mismatch

---

## 15. Deployment Assumptions

BarePitch targets conservative shared hosting.

### 15.1 Runtime Baseline

The project must remain compatible with:
- PHP
- MySQL
- plain CSS
- vanilla JavaScript

The project must not require:
- Node.js
- Laravel
- Docker
- Redis
- frontend build pipeline

### 15.2 Public Document Root

The web server document root should point to `public/` where possible.

Files outside `public/` should not be directly web-accessible.

If hosting does not allow this structure, deployment must provide equivalent protection.

### 15.3 Writable Directories

Any writable directories must be explicitly documented.

Writable directories must not allow execution of uploaded or generated code.

### 15.4 Cron Availability

The MVP must not require cron to function.

If cron is available, it may be used later for:
- cleanup of expired tokens
- backup scheduling
- stale lock cleanup
- derived data checks

Expired tokens and locks must still be treated as invalid even before cleanup runs.

---

## 16. Release Readiness Checklist

Before a formal release:

- migrations have been applied cleanly
- seed or setup path has been verified
- automated tests pass
- PHP syntax checks pass
- MVP acceptance scenario passes when releasing `v1.0.0`
- authorization has been reviewed
- CSRF protection has been reviewed
- public token routes have been reviewed
- security headers have been verified
- production error display is disabled
- backups are configured
- at least one restore test has been performed for MVP release
- temporary developer login is disabled outside local development
- documentation is aligned with implementation
- release notes are prepared

---

## 17. AI Implementation Notes

AI must not silently invent operational behavior.

When implementation changes any of the following, update this document:
- environment configuration
- migration policy
- seed data behavior
- backup expectations
- error logging behavior
- audit-log governance
- deployment assumptions
- stale form or lock behavior
- repair/recalculation operations

AI must prefer documented conservative choices over convenience shortcuts.

AI must not treat manual database edits as normal product behavior.

---

## End


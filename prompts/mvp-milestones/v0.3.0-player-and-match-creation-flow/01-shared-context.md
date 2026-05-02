# Shared Context — v0.3.0 Player and Match Creation Flow

# Purpose
Reusable reference for all prompts in the v0.3.0 bundle. Read this before executing any subsequent prompt in this directory.

---

## Milestone Identity

**Version**: v0.3.0  
**Name**: Player and Match Creation Flow  
**Goal**: Make the team setup and planned-match setup flows stable enough for repeated use. All player management and match creation must be properly authorized, validated, and tested.

---

## Technical Stack

- **Backend**: PHP (no framework)
- **Database**: MySQL with PDO prepared statements
- **Frontend**: Plain CSS, vanilla JavaScript
- **No**: Laravel, Node.js, Docker, Redis, frontend frameworks, build pipeline

---

## Architecture

Request flow: **Route → Controller → Validator → Policy → Service → Repository → View**

Same layer rules as prior milestones — see v0.1.0/01-shared-context.md for the full table. Key reminders:
- Controllers: HTTP orchestration only; no SQL, no business rules
- Services: own all business logic and transactions
- Repositories: scoped DB access via PDO prepared statements

---

## What Prior Milestones Established

- **v0.1.0**: Project skeleton, schema, seed data, minimal team context (dev bypass), match creation stub, preparation stub, live match, summary
- **v0.2.0**: Production magic-link authentication, secure sessions, team context with role loading, CSRF middleware, route protection middleware

All routes in this milestone must go through the real authentication and CSRF middleware from v0.2.0.

---

## Critical Invariants

- **Player deactivation is a soft delete** — historical match event references must be preserved; never delete player rows
- **Player season context** — players only appear in active selection lists when they have a `player_season_context` record for the active team/season
- **External guests persist independently** — they are not normal squad members and must not appear in the regular squad list
- **Match belongs to active team** — every match query must be scoped to the active team
- **Phase-team validation** — a match's phase must belong to the active team's season; validated server-side
- **Planned match editability** — only `planned` matches can be edited; other states reject edits

---

## Authorization Matrix for This Milestone

| Action | coach | trainer | administrator | team_manager |
|---|---|---|---|---|
| View players | ✓ | ✓ | ✓ | ✓ |
| Create/edit/deactivate players | ✗ | ✗ | ✓ | ✓ |
| View matches | ✓ | ✓ | ✓ | ✓ |
| Create/edit matches | ✓ | ✗ | ✓ | ✗ |
| Admin setup (club/season/phase/team/user/role) | ✗ | ✗ | ✓ | ✗ |

---

## Coding Philosophy

- No invention — do not add roles, player statuses, or match fields not in the docs
- Every write path: authenticate → authorize → validate input → validate domain state → transact → fail safely
- Feature is not complete until: authorization enforced, validation enforced, tests exist

---

## Global Exclusions for This Milestone

- Match preparation beyond stub links
- Live match features beyond existing v0.1.0 behavior
- Advanced player statistics
- Ratings, player photos, training
- Parent/contact data

---

## Required Documentation

- `docs/BarePitch-v2-00-documentation-map.md`
- `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md`
- `docs/BarePitch-v2-02-authorization-matrix-v1.0.md`
- `docs/BarePitch-v2-03-system-architecture-v1.0.md`
- `docs/BarePitch-v2-05-critical-behavior-specifications-v1.0.md`
- `docs/BarePitch-v2-06-mvp-scope-v1.0.md`
- `docs/BarePitch-v2-07-functional-scope-guide-v1.0.md`
- `docs/BarePitch-v2-08-route-api-specification-v1.0.md`
- `docs/BarePitch-v2-09-ui-interaction-specifications-v1.0.md`
- `docs/BarePitch-v2-10-data-integrity-test-scenarios-v1.0.md`
- `docs/BarePitch-v2-11-implementation-planning-v1.0.md`
- `docs/BarePitch-v2-12-ai-implementation-rules-v1.0.md`

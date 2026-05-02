# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

---

## Source of Truth

All implementation decisions are governed by the documentation in `docs/`. When documents conflict, use the precedence rules in:

**`docs/BarePitch-v2-00-documentation-map.md`** — read this first.

The precedence order (highest to lowest):
1. Domain model and schema (`-01`)
2. Authorization matrix (`-02`)
3. System architecture (`-03`)
4. State and derived data policy (`-04`)
5. Critical behavior specifications (`-05`)
6. MVP scope (`-06`)
7. Functional scope guide (`-07`)
8. Route and API specification (`-08`)
9. UI interaction specifications (`-09`)
10. Data integrity and test scenarios (`-10`)
11. Implementation planning (`-11`)
12. AI implementation rules (`-12`)
13. Operational and implementation details (`-15`)
14. Git workflow (`-13`)
15. Versioning and milestones (`-14`)

If two documents conflict, update the docs before proceeding — do not interpret ad hoc.

---

## Project Overview

BarePitch is a PHP/MySQL football match management app for coaches. It is designed for **shared hosting** with no build pipeline, no framework, no Docker, no Node.js.

Stack: **PHP · MySQL · Plain CSS · Vanilla JS**

Current status: documentation and pre-MVP planning. No runnable code yet.

---

## Architecture

The request flow is:

```
Route → Controller → Validator → Policy → Service → Repository → View
```

Layer rules (see `-03` for full detail):
- **Controllers** — HTTP orchestration only; no business rules, no SQL
- **Validators** (`app/Http/Requests/`) — input shape and enum membership; no permissions, no DB writes
- **Policies** (`app/Policies/`) — server-side authorization; no mutation
- **Services** (`app/Services/`) — own all business logic, state transitions, transactions, derived-data recalculation, audit writes
- **Repositories** (`app/Repositories/`) — scoped DB access; no authorization, no business transitions
- **Domain** (`app/Domain/`) — enums, value objects, constants
- **Views** (`app/Views/`) — render state only; no business decisions

**Transaction rule**: only Services may open business transactions. Controllers must never open domain transactions directly.

**Dependency direction**: Controllers → Validators/Policies/Services → Repositories/Domain. No reverse dependencies. No Controller → raw SQL.

---

## Implementation Rules

Read `docs/BarePitch-v2-12-ai-implementation-rules-v1.0.md` before implementing any feature. Key rules:

**No invention**: do not silently invent new roles, match states, token types, enums, permission shortcuts, or destructive behaviors. If a required detail is missing, stop and surface the gap.

**Every write path must**: authenticate → authorize → validate input shape → validate text lengths → validate domain state → transact → recalculate derived data → fail safely without partial state.

**Feature is not complete until**: behavior implemented, authorization enforced, validation enforced, free-text limits enforced, errors fail safely, derived state correct, tests exist, docs updated if behavior changed.

**Security**: use prepared statements, CSRF on all state-changing routes, hashed token storage, server-side authorization, scoped resource lookup. Never trust UI hiding for permissions.

**When docs conflict**: stop — do not silently compromise. Update the docs or ask for clarification.

---

## Git Workflow

See `docs/BarePitch-v2-13-solo-development-git-workflow-v1.0.md` for full rules. Summary:

- `main` is canonical; use short-lived `feature/*`, `fix/*`, `docs/*`, `refactor/*`, `chore/*` branches for non-trivial changes
- Create a GitHub issue before starting any change that affects behavior, security, schema, or permissions
- Commit at coherent checkpoints — one completed behavior, schema, or permission change per commit
- Commit messages: imperative, specific, include issue number when available (`Fix #24: require recent auth for role changes`)
- Code and docs must agree before merging to `main`
- Push at least once per active work session

**Before merging to `main`**: issue scope satisfied · commit history readable · docs and code agree · no debug shortcuts left enabled · any migration intentional and documented.

---

## Development Commands

The project has no build pipeline. Once PHP is implemented:

- **Local server**: `php -S localhost:8000 -t public/` (or configure Apache/Nginx with `public/` as document root)
- **Environment**: copy `.env.example` to `.env` and fill in `DB_*` values
- **Tests**: test runner TBD (likely PHPUnit — add `composer.json` when scaffolding begins)
- **Migrations**: run ordered files in `database/migrations/` manually or via a migration script in `scripts/`

---

## Milestone Prompts

Executable implementation prompts are in `prompts/mvp-milestones/`. They split the MVP path from `v0.1.0` (first vertical slice) to `v1.0.0` (MVP release). Start with `prompts/mvp-milestones/README.md`.

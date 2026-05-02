# Shared Context — v0.1.0 First Working Vertical Slice

# Purpose
Reusable reference for all prompts in the v0.1.0 bundle. Read this before executing any subsequent prompt in this directory.

---

## Milestone Identity

**Version**: v0.1.0  
**Name**: First Working Vertical Slice  
**Goal**: Build the smallest complete flow that proves BarePitch can run locally from setup through a finished basic match summary.

This milestone may use a temporary local developer login to bypass email delivery, but it **must not be production-enabled**.

---

## Technical Stack

- **Backend**: PHP (no framework — no Laravel, Symfony, or similar)
- **Database**: MySQL, accessed via PDO with prepared statements only
- **Frontend**: Plain CSS, vanilla JavaScript
- **No**: Node.js, Docker, Redis, frontend frameworks (React, Vue, etc.), build pipeline (Webpack, Vite, etc.)
- **Target**: Shared hosting

---

## Architecture

Request flow: **Route → Controller → Validator → Policy → Service → Repository → View**

| Layer | Location | Responsibility | Must NOT |
|---|---|---|---|
| Controllers | `app/Http/Controllers/` | HTTP orchestration only | contain business rules, open transactions, write SQL |
| Validators | `app/Http/Requests/` | Input shape and enum membership | check permissions or write to DB |
| Policies | `app/Policies/` | Server-side authorization | mutate state |
| Services | `app/Services/` | Business logic, state transitions, transactions, derived-data recalculation | be bypassed |
| Repositories | `app/Repositories/` | Scoped DB access via PDO | perform authorization or business transitions |
| Domain | `app/Domain/` | Enums, value objects, constants | contain I/O |
| Views | `app/Views/` | Render state only | make business decisions |

**Transaction rule**: Only Services may open business transactions. Controllers must never open domain transactions directly.

**Dependency direction**: Controllers → Validators/Policies/Services → Repositories/Domain. No reverse dependencies. No Controller → raw SQL.

---

## Critical Invariants

- **Server is authoritative for match state and score** — the client never determines match state
- **Score is recalculated from match events** — never blindly incremented as a stored counter
- **Match states** are only: `planned`, `prepared`, `active`, `finished`
- **A match cannot start unless prepared** — attempting to start a planned match returns a safe error
- **A finished match remains viewable and must not restart** — the `finished` state is terminal
- **Bench players have no lineup coordinates** — `x_coord` and `y_coord` are `null` for bench players
- **One active field slot may contain at most one player** — duplicate slot assignments are rejected

---

## Coding Philosophy

- **No invention**: do not silently invent new roles, match states, token types, enums, or permission shortcuts. If a required detail is missing, stop and surface the gap.
- **Every write path must**: authenticate → authorize → validate input shape → validate text lengths → validate domain state → transact → recalculate derived data → fail safely without partial state.
- **Feature is not complete until**: behavior implemented, authorization enforced, validation enforced, free-text limits enforced, errors fail safely, derived state correct, tests exist, docs updated if behavior changed.
- **Security**: use prepared statements, CSRF on all state-changing routes, server-side authorization. Never trust UI hiding for permissions.
- **When docs conflict**: stop — do not silently compromise. Update the docs or ask for clarification.

---

## UI Requirements

- Mobile-first layout
- Stable, calm hierarchy
- Use bottom navigation only for durable top-level destinations
- Use contextual in-screen controls for live match actions
- Critical transitions need confirmation; do not rely on icon-only controls for destructive or state-changing critical actions

---

## Global Exclusions for This Milestone

Do not implement:
- Full magic-link email delivery (use local dev log instead)
- Ratings
- Training
- Dashboard refinements
- Livestream
- Finished-match correction UI
- Extra time
- Penalty shootout
- Advanced statistics

---

## Required Documentation

Read before implementing any feature in this bundle:

- `docs/BarePitch-v2-00-documentation-map.md`
- `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md`
- `docs/BarePitch-v2-02-authorization-matrix-v1.0.md`
- `docs/BarePitch-v2-03-system-architecture-v1.0.md`
- `docs/BarePitch-v2-04-state-and-derived-data-policy-v1.0.md`
- `docs/BarePitch-v2-05-critical-behavior-specifications-v1.0.md`
- `docs/BarePitch-v2-06-mvp-scope-v1.0.md`
- `docs/BarePitch-v2-08-route-api-specification-v1.0.md`
- `docs/BarePitch-v2-09-ui-interaction-specifications-v1.0.md`
- `docs/BarePitch-v2-10-data-integrity-test-scenarios-v1.0.md`
- `docs/BarePitch-v2-11-implementation-planning-v1.0.md`
- `docs/BarePitch-v2-12-ai-implementation-rules-v1.0.md`

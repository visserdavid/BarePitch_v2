# Shared Context — v0.4.0 Match Preparation

# Purpose
Reusable reference for all prompts in the v0.4.0 bundle. Read this before executing any subsequent prompt in this directory.

---

## Milestone Identity

**Version**: v0.4.0  
**Name**: Match Preparation  
**Goal**: Make the planned-to-prepared match transition reliable and enforceable. The server must validate every preparation rule and reject any invalid lineup before transitioning a match to the `prepared` state.

---

## Technical Stack

- **Backend**: PHP (no framework)
- **Database**: MySQL with PDO prepared statements
- **Frontend**: Plain CSS, vanilla JavaScript (may assist UI only — server is authoritative)
- **No**: Laravel, Symfony, Node.js, Docker, Redis, frontend frameworks, build pipeline

---

## Architecture

Request flow: **Route → Controller → Validator → Policy → Service → Repository → View**

| Layer | Rule |
|---|---|
| Controllers | HTTP orchestration only; no business rules, no SQL |
| Validators | Input shape and enum membership; no permissions, no DB writes |
| Policies | Server-side authorization; no mutation |
| Services | Own all business logic, state transitions, transactions |
| Repositories | Scoped DB access; no authorization |
| Views | Render state only |

**Transaction rule**: Only Services may open business transactions. The prepare match action must be a single atomic transaction in `MatchPreparationService`.

---

## What Prior Milestones Established

- **v0.1.0**: Project skeleton, schema, seed data, match creation, minimal preparation stub, live match, match summary
- **v0.2.0**: Real magic-link authentication, secure sessions, team context, route protection, CSRF middleware
- **v0.3.0**: Player management (list/create/edit/deactivate), external guest players, full match creation flow (planned state)

This milestone makes the preparation flow complete and production-ready.

---

## Critical Invariants

**Server is authoritative — always**:
- JavaScript may assist with drag-and-drop UI, grid visualization, and optimistic UI updates
- Every preparation rule is re-validated on the server before the `prepared` state transition
- Client-submitted lineup data is validated server-side — never trusted without verification

**Lineup stores current state only** (not historical replay):
- `match_lineup` represents who is currently in each position right now
- It is not an event log; it is overwritten by substitutions and other lineup changes
- Bench players have `null` (or equivalent) for coordinates

**One player per active field slot** — two players cannot occupy the same position simultaneously.

**Bench players have no coordinates** — bench assignment stores the player without position coordinates.

**Prepare transition rules** (all must pass before state can become `prepared`):
1. Match is in `planned` state
2. At least 11 players marked present
3. Total present players ≤ maximum allowed (per docs)
4. A formation is selected
5. All starting positions for the formation are filled
6. Every starter is marked present (not absent, not injured)
7. No injured player is in a starting position
8. No duplicate player in multiple starting positions
9. No duplicate field slot occupied by multiple players

If any rule fails: return a descriptive error; match remains in `planned` state; no partial state written.

**Internal guests must come from another team within the same club** — not from a different club.

**External guests persist** and are reused across matches.

---

## Coding Philosophy

- No silent invention of new positions, formation types, or player statuses
- Every write path: authenticate → authorize → validate input → validate domain state → transact → fail safely
- Feature is not complete until: all preparation rules enforced server-side, authorization enforced, CSRF on all writes, tests exist

---

## Global Exclusions for This Milestone

- Full live match expansion beyond start support
- Substitution flow
- Cards
- Penalties
- Penalty shootout
- Livestream
- Finished-match corrections

---

## Required Documentation

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

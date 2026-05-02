# Shared Context — v0.2.0 Authentication and Team Context

# Purpose
Reusable reference for all prompts in the v0.2.0 bundle. Read this before executing any subsequent prompt in this directory.

---

## Milestone Identity

**Version**: v0.2.0  
**Name**: Authentication and Team Context  
**Goal**: Replace the temporary developer login from v0.1.0 with a documented magic-link authentication system. Users must authenticate through one-time expiring tokens, then operate inside an authorized team context.

---

## Technical Stack

- **Backend**: PHP (no framework)
- **Database**: MySQL with PDO prepared statements
- **Frontend**: Plain CSS, vanilla JavaScript
- **No**: Laravel, Symfony, Node.js, Docker, Redis, frontend frameworks, build pipeline
- **Target**: Shared hosting

---

## Architecture

Request flow: **Route → Controller → Validator → Policy → Service → Repository → View**

| Layer | Responsibility | Must NOT |
|---|---|---|
| Controllers (`app/Http/Controllers/`) | HTTP orchestration only | contain business rules, SQL, or auth decisions |
| Validators (`app/Http/Requests/`) | Input shape and enum membership | check permissions or write to DB |
| Policies (`app/Policies/`) | Server-side authorization | mutate state |
| Services (`app/Services/`) | Business logic, state transitions, transactions, derived-data recalculation | be bypassed by controllers |
| Repositories (`app/Repositories/`) | Scoped DB access via PDO | perform authorization or business transitions |
| Domain (`app/Domain/`) | Enums, value objects, constants | contain I/O |
| Views (`app/Views/`) | Render state only | make business decisions |

**Transaction rule**: Only Services may open business transactions. Controllers must never open domain transactions directly.

**Dependency direction**: Controllers → Validators/Policies/Services → Repositories/Domain. No reverse dependencies.

---

## What v0.1.0 Established

- Project skeleton: directory structure, autoloader, router, PDO connection, error handler
- Database schema: all core MVP tables installed
- Seed data: one club, season, phase, team, user, formation, 16+ players
- Team context helpers (with temporary developer bypass for `APP_ENV=local`)
- Player list, match creation, preparation, live match, match summary

This milestone replaces the temporary bypass with the documented authentication system.

---

## Authentication Critical Invariants

- **Magic-link tokens are stored as hashes only** — never plaintext. Use `hash('sha256', $token)` or equivalent. Never log, store, or transmit the raw token after generation.
- **Tokens are one-time use** — once consumed at the callback, mark as used; subsequent use of the same token returns a generic failure.
- **Tokens expire** — per the docs specification; expired tokens return a generic failure.
- **Generic failure messages** — expired, used, and non-existent tokens must all return the same generic message. Do not reveal whether the token existed or was expired.
- **Sessions use secure settings** — HttpOnly, SameSite=Lax, Secure (when HTTPS), non-default session name, proper idle/absolute lifetimes.
- **Session ID regenerated on login** — call `session_regenerate_id(true)` after successful authentication to prevent session fixation.
- **Protected routes reject unauthenticated users** — redirect to `/login`, not a 403 or blank page.
- **UI hiding is NOT sufficient** — all authorization is enforced server-side regardless of what the UI shows or hides.
- **Temporary developer login active only when `APP_ENV=local`** — never active in staging or production.

---

## Team Context Critical Invariants

- A user with no team roles sees a safe "no access" page and cannot reach any protected route.
- A user with multiple teams must explicitly select an active team before accessing protected routes.
- Selecting a team the user does not have a role for is rejected server-side.
- Roles (coach, trainer, administrator, team_manager) are loaded from `user_team_role` into session after login.

---

## Coding Philosophy

- **No invention**: do not add roles, token types, session keys, or security behaviors not documented in the spec.
- **Every write path must**: authenticate → authorize → validate input shape → validate text lengths → validate domain state → transact → fail safely.
- **Feature is not complete until**: behavior implemented, authorization enforced, validation enforced, errors fail safely, tests exist.
- **Security**: prepared statements everywhere, CSRF on all state-changing routes, hashed token storage, server-side authorization.
- **When docs conflict**: stop — do not silently compromise. Update docs or ask for clarification.

---

## Global Exclusions for This Milestone

- Social login
- Password login
- Public registration
- Multi-factor authentication
- Non-MVP identity features
- Any feature beyond authentication and team context

---

## Required Documentation

Read before implementing any feature in this bundle:

- `docs/BarePitch-v2-00-documentation-map.md`
- `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md`
- `docs/BarePitch-v2-02-authorization-matrix-v1.0.md`
- `docs/BarePitch-v2-03-system-architecture-v1.0.md`
- `docs/BarePitch-v2-06-mvp-scope-v1.0.md`
- `docs/BarePitch-v2-07-functional-scope-guide-v1.0.md`
- `docs/BarePitch-v2-08-route-api-specification-v1.0.md`
- `docs/BarePitch-v2-10-data-integrity-test-scenarios-v1.0.md`
- `docs/BarePitch-v2-11-implementation-planning-v1.0.md`
- `docs/BarePitch-v2-12-ai-implementation-rules-v1.0.md`

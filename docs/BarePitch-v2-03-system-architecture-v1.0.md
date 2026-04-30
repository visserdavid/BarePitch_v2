# BarePitch — System Architecture
Version 1.0 — April 2026

---

## 1. Purpose

This document defines the canonical application architecture for BarePitch.

It is the source of truth for:
- application layers
- responsibility boundaries
- dependency rules
- transaction ownership
- validation ownership
- authorization ownership

---

## 2. Architectural Goals

The architecture must optimize for:
- correctness under live-match pressure
- maintainability in a small PHP/MySQL codebase
- explicit business rules
- low accidental complexity
- compatibility with shared hosting

It must avoid:
- framework-shaped complexity without framework benefits
- duplicated rules across route handlers
- hidden write side effects

---

## 3. High-Level Shape

BarePitch is a server-rendered web monolith with light JavaScript enhancement.

Core runtime parts:
- router
- controllers
- request validators
- authorization policies
- services
- repositories
- views/templates
- lightweight client-side interaction scripts

Canonical flow:

1. Route receives request
2. Controller resolves current user and team context
3. Validator checks input shape
4. Policy checks permission
5. Service executes business action
6. Repository reads/writes persistence
7. Service recalculates derived state if required
8. Controller returns redirect, HTML, or JSON

---

## 4. Suggested Directory Boundaries

Recommended structure:

- `/public`
- `/app/Config`
- `/app/Core`
- `/app/Http/Controllers`
- `/app/Http/Requests`
- `/app/Policies`
- `/app/Services`
- `/app/Repositories`
- `/app/Domain`
- `/app/Views`
- `/public/js`
- `/public/css`

Purpose summary:
- `Controllers`: HTTP orchestration
- `Requests`: request validation objects or functions
- `Policies`: permission logic
- `Services`: business logic and transactional use cases
- `Repositories`: database persistence access
- `Domain`: enums, value objects, constants, calculators where useful
- `Views`: templates and presentational fragments

---

## 5. Layer Responsibilities

### 5.1 Controllers

Controllers must:
- parse route parameters
- invoke validators
- invoke policies
- call services
- select response type

Controllers must not:
- embed business rules
- perform multi-entity write logic
- run raw SQL
- recalculate match state directly

### 5.2 Request Validators

Validators must:
- validate input shape
- validate required fields
- validate enum membership
- normalize safe request data where needed

Validators must not:
- decide permissions
- perform domain state transitions
- write to the database

### 5.3 Policies

Policies must:
- answer whether the actor may perform the action
- use team scope, role scope, and state scope

Policies must not:
- mutate data
- trigger recalculation
- format UI errors

### 5.4 Services

Services must:
- own business rules
- own state transitions
- own transactional writes
- own recalculation of cached state
- coordinate multiple repositories
- emit audit writes where required

Services must not:
- know HTML or template concerns
- read directly from global request state

### 5.5 Repositories

Repositories must:
- read and write database records
- expose scoped query methods
- hide SQL details from services

Repositories must not:
- own authorization
- own business transitions
- perform hidden side effects outside explicit persistence

### 5.6 Views and Templates

Views must:
- render state
- show validation and system messages
- expose user actions the backend already authorizes

Views must not:
- contain source-of-truth business decisions
- bypass server-side validation or permission logic

### 5.7 Client-Side JavaScript

JavaScript may:
- improve interaction speed
- open modals
- handle drag/drop
- submit background refresh and polling
- improve optimistic UI after server confirmation

JavaScript must not:
- become the only place where rules exist
- bypass CSRF
- define authorization

---

## 6. Dependency Rules

Allowed dependency direction:

- Controllers -> Validators, Policies, Services
- Services -> Repositories, Domain helpers
- Policies -> Repositories or scoped-read helpers if needed
- Views -> View models or plain data prepared by controller/service

Disallowed:

- Repository -> Controller
- Service -> View
- Policy -> View
- Template -> Repository
- Controller -> raw SQL

---

## 7. Transaction Ownership

Only services may open business transactions.

Transactions are required for:
- match start
- period transitions
- live event writes
- substitutions
- red cards
- penalty shootout attempts
- match finish
- finished-match corrections
- any write that changes both source data and cached state

Controllers must never open domain transactions directly unless delegating immediately to a service wrapper.

---

## 8. Validation and Authorization Order

Recommended order:

1. authenticate
2. load resource in accessible scope where possible
3. authorize
4. validate input shape
5. validate domain state in service
6. mutate in transaction

Notes:
- cheap request-shape validation may run before full resource loading when safe
- domain-state validation belongs in service, not only request validator

---

## 9. Read Model vs Write Model

BarePitch should treat some reads as simple presentation reads and some writes as business use cases.

Read side:
- lists
- detail pages
- dashboards
- public livestream pages

Write side:
- match preparation
- live match control
- corrections
- role changes
- token rotation

Write use cases must always use service-layer orchestration.

---

## 10. Domain Service Areas

Recommended service ownership:

- `AuthService`
- `TeamContextService`
- `PlayerService`
- `TrainingService`
- `MatchService`
- `MatchPreparationService`
- `LiveMatchService`
- `CorrectionService`
- `LivestreamService`
- `StatisticsService`
- `AuditService`
- `LockService`

Recommended repository ownership:

- `UserRepository`
- `TeamRepository`
- `PlayerRepository`
- `TrainingRepository`
- `MatchRepository`
- `LineupRepository`
- `MatchEventRepository`
- `SubstitutionRepository`
- `ShootoutRepository`
- `AuditLogRepository`
- `TokenRepository`
- `LockRepository`

---

## 11. Forbidden Patterns

Do not:
- update cached score without recalculating from source records
- recalculate business state in templates
- keep separate hidden business logic in JavaScript
- check permissions only at route visibility level
- duplicate the same domain rule in multiple controllers
- expose direct database repair paths in user-facing code

---

## 12. Error Handling Placement

- validators produce validation failures
- policies produce authorization failures
- services produce domain-state failures
- repositories surface persistence failures
- controllers map failures to HTTP/HTML/JSON responses

Public token endpoints must still use generic user-facing failures even if services know the exact reason.

---

## 13. AI Implementation Rules for Architecture

When generating code:
- create or reuse a service for each non-trivial write action
- place authorization in policy or explicit service guard, not only route visibility
- place recalculation in dedicated service methods
- create repositories with scoped query methods
- avoid “god controllers”

If a feature would violate this architecture:
- stop and update this document first

---

## End

# BarePitch Documentation Map
Version 1.0 — April 2026

---

## 1. Purpose

This file defines the role of each document in `/docs`.

The goal is to keep the documentation set maintainable by giving each document one clear job.

When documents overlap, use the source-of-truth rules below.

---

## 2. Source of Truth Rules

### Functional Scope

File:
- `BarePitch-v2-07-functional-scope-guide-v1.0.md`

Owns:
- full product capability
- user roles
- feature boundaries
- non-MVP functional modules

Does not own:
- implementation order
- route details
- test scenarios
- low-level live match behavior

### MVP Scope

File:
- `BarePitch-v2-06-mvp-scope-v1.0.md`

Owns:
- what is in MVP
- what is explicitly out of MVP
- MVP success criteria
- MVP release boundaries

Does not own:
- build order
- route design
- full future scope

### Implementation Planning

File:
- `BarePitch-v2-11-implementation-planning-v1.0.md`

Owns:
- build sequence
- phase ordering
- vertical slice strategy
- release preparation sequence

Does not own:
- final product boundaries
- route contracts
- business rules outside implementation order

### Critical Behavior

File:
- `BarePitch-v2-05-critical-behavior-specifications-v1.0.md`

Owns:
- authoritative match behavior
- state transitions
- event behavior
- substitution, card, shootout, correction, and locking rules

Does not own:
- route paths
- test coverage inventory

### Route and API Specification

File:
- `BarePitch-v2-08-route-api-specification-v1.0.md`

Owns:
- HTTP routes
- request fields
- response behavior
- endpoint-level permission checks

Must follow:
- critical behavior rules
- MVP and functional scope boundaries

### Data Integrity and Test Scenarios

File:
- `BarePitch-v2-10-data-integrity-test-scenarios-v1.0.md`

Owns:
- integrity rules
- source-of-truth tables
- regression scenarios
- acceptance scenarios

Does not define new behavior unless the behavior document is updated too.

### UI Interaction Specifications

File:
- `BarePitch-v2-09-ui-interaction-specifications-v1.0.md`

Owns:
- interaction patterns
- mobile behavior
- modal, swipe, and feedback rules
- layout and hierarchy constraints

Does not own:
- permissions
- backend state rules
- route contracts

### Domain Model and Schema

File:
- `BarePitch-v2-01-domain-model-and-schema-v1.0.md`

Owns:
- canonical entities
- fields and relationships
- uniqueness and nullability constraints
- schema invariants
- token and lock persistence structures

Does not own:
- route behavior
- UI behavior
- implementation ordering

### Authorization Matrix

File:
- `BarePitch-v2-02-authorization-matrix-v1.0.md`

Owns:
- resource-action permission rules
- role matrix
- recent-authentication requirements
- audit expectations for sensitive actions

Does not own:
- route transport details
- entity schema
- UI visibility behavior

### System Architecture

File:
- `BarePitch-v2-03-system-architecture-v1.0.md`

Owns:
- application layers
- service/controller/repository boundaries
- transaction ownership
- dependency rules
- forbidden architectural patterns

Does not own:
- product scope
- entity schema
- route list

### State and Derived Data Policy

File:
- `BarePitch-v2-04-state-and-derived-data-policy-v1.0.md`

Owns:
- source-of-truth vs cached-data rules
- recalculation triggers
- stale-state detection
- repair expectations

Does not own:
- permission rules
- UI behavior
- deployment operations

### AI Implementation Rules

File:
- `BarePitch-v2-12-ai-implementation-rules-v1.0.md`

Owns:
- AI documentation precedence during implementation
- anti-invention rules
- required implementation discipline
- code-and-doc update discipline

Does not own:
- product scope
- schema definitions
- route contracts

### Solo Development Git Workflow

File:
- `BarePitch-v2-13-solo-development-git-workflow-v1.0.md`

Owns:
- solo branch and commit workflow
- issue creation rules
- push and merge discipline
- documentation update expectations in Git workflow

Does not own:
- product behavior
- architecture boundaries
- authorization rules

### Project Versioning and Milestones

File:
- `BarePitch-v2-14-project-versioning-and-milestones-v1.0.md`

Owns:
- release version format
- milestone-to-version mapping
- MVP release version number
- release bump rules

Does not own:
- feature scope
- issue workflow details
- deployment mechanics

### Operational and Implementation Details

File:
- `BarePitch-v2-15-operational-and-implementation-details-v1.0.md`

Owns:
- environment configuration policy
- migration policy
- seed data policy
- backup and restore expectations
- error logging policy
- audit-log governance
- deployment assumptions
- stale form and operational conflict handling
- release readiness checklist

Does not own:
- domain entities
- permission rules
- route contracts
- critical match behavior
- UI interaction rules

---

## 3. Cross-Document Precedence

When two documents appear to disagree, use this order:

1. `BarePitch-v2-01-domain-model-and-schema-v1.0.md` for canonical entities and schema truth
2. `BarePitch-v2-02-authorization-matrix-v1.0.md` for permission truth
3. `BarePitch-v2-03-system-architecture-v1.0.md` for layer and responsibility truth
4. `BarePitch-v2-04-state-and-derived-data-policy-v1.0.md` for source-of-truth and cache ownership
5. `BarePitch-v2-05-critical-behavior-specifications-v1.0.md` for live match behavior
6. `BarePitch-v2-06-mvp-scope-v1.0.md` for MVP inclusion or exclusion
7. `BarePitch-v2-07-functional-scope-guide-v1.0.md` for full product intent
8. `BarePitch-v2-08-route-api-specification-v1.0.md` for endpoint contract
9. `BarePitch-v2-09-ui-interaction-specifications-v1.0.md` for interaction design
10. `BarePitch-v2-10-data-integrity-test-scenarios-v1.0.md` for verification expectations
11. `BarePitch-v2-11-implementation-planning-v1.0.md` for build order
12. `BarePitch-v2-12-ai-implementation-rules-v1.0.md` for AI implementation discipline
13. `BarePitch-v2-15-operational-and-implementation-details-v1.0.md` for operational safeguards, deployment, migrations, backups, logging, and environment policy
14. `BarePitch-v2-13-solo-development-git-workflow-v1.0.md` for Git and issue workflow discipline
15. `BarePitch-v2-14-project-versioning-and-milestones-v1.0.md` for release versioning and milestone numbering

If a conflict still exists, the docs need to be updated rather than interpreted ad hoc.

---

## 4. Current Documentation Decisions

The current set uses these explicit decisions:

- Training support is part of full product scope, but not required for MVP release.
- Ratings are full-scope functionality, but not required for MVP release.
- Dashboard is useful for MVP only in a minimal form and is not part of the first vertical slice.
- Match period behavior is authoritative in the behavior document and must be represented explicitly in the data model.
- Finished-match correction coverage includes event edits, substitution edits, and shootout edits.
- Operational details such as migrations, backups, environment configuration, and logging are documented separately from product behavior.
- The numbered filenames reflect intended implementation precedence, but the precedence rules in this document remain authoritative if numbering is ever changed later.

---

## 5. Recommended Update Discipline

When adding or changing a feature:

1. Update `BarePitch-v2-01-domain-model-and-schema-v1.0.md` if the entity model or persistence shape changes.
2. Update `BarePitch-v2-02-authorization-matrix-v1.0.md` if permissions or recent-auth requirements change.
3. Update `BarePitch-v2-03-system-architecture-v1.0.md` if implementation boundaries or layer ownership change.
4. Update `BarePitch-v2-04-state-and-derived-data-policy-v1.0.md` if source-of-truth or recalculation rules change.
5. Update `BarePitch-v2-07-functional-scope-guide-v1.0.md` if full product scope changes.
6. Update `BarePitch-v2-06-mvp-scope-v1.0.md` if MVP inclusion changes.
7. Update `BarePitch-v2-05-critical-behavior-specifications-v1.0.md` if match logic changes.
8. Update `BarePitch-v2-08-route-api-specification-v1.0.md` if HTTP behavior changes.
9. Update `BarePitch-v2-09-ui-interaction-specifications-v1.0.md` if interaction behavior changes.
10. Update `BarePitch-v2-10-data-integrity-test-scenarios-v1.0.md` with regression coverage.
11. Update `BarePitch-v2-11-implementation-planning-v1.0.md` only if phase ordering changes.
12. Update `BarePitch-v2-12-ai-implementation-rules-v1.0.md` if AI implementation discipline or precedence rules change.
13. Update `BarePitch-v2-15-operational-and-implementation-details-v1.0.md` if environment config, migrations, seed data, backups, logging, deployment, stale form handling, or operational safeguards change.
14. Update `BarePitch-v2-13-solo-development-git-workflow-v1.0.md` if branch, commit, issue, push, or merge discipline changes.
15. Update `BarePitch-v2-14-project-versioning-and-milestones-v1.0.md` if release numbering or milestone version semantics change.

---

## End

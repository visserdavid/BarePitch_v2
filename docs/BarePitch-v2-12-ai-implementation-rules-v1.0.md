# BarePitch — AI Implementation Rules
Version 1.0 — April 2026

---

## 1. Purpose

This document defines the rules that AI-based implementation must follow when building BarePitch.

It is the source of truth for:
- documentation precedence during implementation
- anti-invention rules
- required implementation discipline
- required update discipline

---

## 2. Primary Goal

AI must implement the documented system faithfully.

AI must not optimize for speed at the expense of:
- correctness
- consistency
- auditability
- security
- maintainability

---

## 3. Documentation Precedence

Use this precedence order:

1. `BarePitch-v2-01-domain-model-and-schema-v1.0.md`
2. `BarePitch-v2-02-authorization-matrix-v1.0.md`
3. `BarePitch-v2-03-system-architecture-v1.0.md`
4. `BarePitch-v2-04-state-and-derived-data-policy-v1.0.md`
5. `BarePitch-v2-05-critical-behavior-specifications-v1.0.md`
6. `BarePitch-v2-06-mvp-scope-v1.0.md`
7. `BarePitch-v2-07-functional-scope-guide-v1.0.md`
8. `BarePitch-v2-08-route-api-specification-v1.0.md`
9. `BarePitch-v2-09-ui-interaction-specifications-v1.0.md`
10. `BarePitch-v2-10-data-integrity-test-scenarios-v1.0.md`
11. `BarePitch-v2-11-implementation-planning-v1.0.md`

If a conflict exists:
- do not silently choose a compromise
- update the docs first or request clarification

---

## 4. No-Invention Rule

AI must not silently invent:
- new roles
- new match states
- new token types
- new status enums
- new derived caches
- new permission shortcuts
- new public data exposure
- new destructive cleanup behavior

If a required implementation detail is missing:
- stop
- identify the missing decision explicitly
- propose the minimum necessary addition

---

## 5. Required Implementation Pattern

For each non-trivial feature, AI must define:
- route or entry point
- validator
- authorization check
- service or use-case logic
- repository access
- transaction rule
- derived-data recalculation rule
- audit behavior if sensitive
- tests

No multi-entity critical write may be implemented as a thin controller-only action.

---

## 6. Write-Path Rules

Every write path must:
- authenticate actor where required
- authorize actor server-side
- validate input shape
- validate documented text lengths for every free-text field
- validate domain state
- use transaction if multiple critical state changes occur
- recalculate affected derived data where required
- fail safely without partial state

Every public token path must:
- avoid revealing token validity details unnecessarily
- respect rate limits and generic failure policy

---

## 7. Security Rules for AI

AI must always:
- use prepared statements or equivalent safe parameter binding
- respect CSRF rules for state-changing routes
- use scoped resource lookup where possible
- hash stored public or login tokens
- enforce secure session rules
- enforce text length limits in validators and schema
- preserve auditability of sensitive actions

AI must never:
- store tokens in plaintext
- rely only on UI hiding for permission
- rely only on HTML `maxlength` for text length enforcement
- expose internal notes publicly
- bypass audit requirements for sensitive corrections or admin changes

---

## 8. UI Rules for AI

AI must:
- preserve mobile-first behavior
- keep stable top-level bottom navigation
- use contextual in-screen controls for live-match subareas
- keep labels on primary bottom navigation items
- keep critical actions non-icon-only

AI must not:
- treat contextual live controls as permanent top-level nav
- introduce dense unreadable control bars
- remove confirmation flows for critical transitions

---

## 9. Testing Rules for AI

Every feature implementation must include tests proportionate to risk.

Minimum expectations:
- validator tests for input rules
- validator tests for documented text length limits on changed free-text fields
- authorization tests for protected writes
- service tests for domain transitions
- integration tests for critical flows

Critical live-match changes must include regression tests for:
- score integrity
- lineup integrity
- red card restrictions
- correction behavior
- audit writes where required

---

## 10. Update Discipline

When AI changes behavior, it must also update the relevant docs.

Minimum rule:
- code and documentation must not knowingly drift

If implementation changes:
- entity shape -> update domain-model doc
- permission -> update authorization matrix
- service boundary -> update architecture doc
- cache/source truth -> update state policy
- product flow -> update behavior or scope doc
- route -> update route spec
- UI interaction -> update UI spec

---

## 11. Clarification Threshold

AI must stop and ask for clarification when:
- two documents conflict materially
- a security-sensitive behavior is unspecified
- a destructive action policy is unclear
- a domain invariant would need to be guessed
- a permission boundary would otherwise be inferred

AI may proceed with documented best practice when:
- the docs explicitly delegate detail
- the choice does not change behavior materially
- the choice stays inside documented constraints

---

## 12. Feature Completion Checklist

A feature is not complete unless:
- behavior is implemented
- authorization is enforced
- validation is enforced
- documented free-text limits are enforced where applicable
- errors fail safely
- derived state remains correct
- tests exist
- docs are updated if behavior changed

---

## 13. Anti-Shortcut Rules

AI must not use these shortcuts:
- “temporary” direct database manipulation as product behavior
- controller-level duplication of service rules
- frontend-only validation for business rules
- raw hidden fields trusted as authoritative state
- partial correction without recalculation
- public debug routes in production-facing code

---

## 14. Success Definition

AI-driven implementation is successful when:
- repeated runs produce consistent architecture
- code follows documented boundaries
- security and state rules survive feature growth
- future AI can continue from the docs without reinterpreting fundamentals

---

## End

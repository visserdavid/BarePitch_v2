# Documentation Alignment — v0.9.0

# Purpose
Compare the current codebase (as implemented through v0.8.0 and this bundle's earlier prompts) against the full documentation set. Identify every place where code behavior and documentation disagree. Update documentation where the code is correct and the doc is wrong. Flag for human review any case where it is unclear which is correct. Never silently change code behavior to match an incorrect document.

---

# Required Context
See `01-shared-context.md`. All prior prompts in this bundle (02 through 06) must be complete before running this pass. This prompt depends on the security, consistency, and authorization fixes already being applied so the comparison is against the final state of the code.

---

# Required Documentation
All documents in the `docs/` directory. The full set for this project is:
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
- `docs/BarePitch-v2-13-solo-development-git-workflow-v1.0.md`
- `docs/BarePitch-v2-14-project-versioning-and-milestones-v1.0.md`
- `docs/BarePitch-v2-15-operational-and-implementation-details-v1.0.md`

---

# Scope

## Alignment Method

For each document, work through its content and compare every normative statement (a "must", "shall", "only", "always", "never") against the codebase. Record disagreements.

Apply the following decision rules to every disagreement:

| Situation | Action |
|---|---|
| Code matches doc | Record as PASS — no change needed |
| Code diverges from doc AND the code is correct per overall system intent | Update the doc to match the code; record the change |
| Code diverges from doc AND the doc is correct per overall system intent | Flag for human review; do NOT silently change the code without noting the discrepancy |
| Code diverges from doc AND it is unclear which is correct | Flag for human review; record both versions; do not change either until clarified |
| Code implements something the doc does not mention | If it is an implementation detail: acceptable, no change needed. If it is a new behavior or permission: add it to the doc |
| Doc specifies something the code does not implement | If it is MVP-required: flag as an implementation gap; open a tracking issue. If it is post-MVP: note it as deferred |

---

## Alignment Areas

### Schema vs. Code (doc -01)

Compare `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md` against the actual migration files in `database/migrations/`.

Check:
- Every table named in the doc exists in a migration
- Every column named in the doc exists in the migration with the correct type and constraints
- No migration adds a column not described in the doc
- Enum values in code (`app/Domain/`) match enum values in the doc
- Foreign key constraints in migrations match the relationships described in the doc

Common drift points:
- Column names added for playing time, shootout scores, or lock versions that may not have been in the original schema doc
- Indexes added for performance that are not documented (these are acceptable to add without doc changes — document them as implementation details if significant)

### Authorization Matrix vs. Code (doc -02)

Compare `docs/BarePitch-v2-02-authorization-matrix-v1.0.md` against the Policy files in `app/Policies/`.

This was already done in prompt 06, but document the comparison result here. If prompt 06 found and fixed policy gaps, record the policy change as a doc update if the doc is wrong, or flag for review if the code change might have been wrong.

Check:
- Every row in the authorization matrix has a corresponding policy method
- The roles in the policy match the roles in the matrix row exactly

### Route Spec vs. Code (doc -08)

Compare `docs/BarePitch-v2-08-route-api-specification-v1.0.md` against `app/routes.php`.

Check:
- Every route in the spec exists in the router
- Every route in the router exists in the spec (or is an implementation detail like a static asset route)
- Route methods match (POST vs. GET vs. PUT)
- Route URL patterns match (including parameter names)
- Authentication and role requirements match between spec and implementation

### State Machine vs. Code (doc -04)

Compare `docs/BarePitch-v2-04-state-and-derived-data-policy-v1.0.md` against `app/Services/` (particularly match state transition services).

Check:
- Every allowed state transition in the doc is implemented in the code
- No state transition in the code is missing from the doc
- Derived data recalculation triggers match what the doc specifies

### Critical Behaviors vs. Code (doc -05)

Compare `docs/BarePitch-v2-05-critical-behavior-specifications-v1.0.md` against the relevant services.

Focus on:
- Magic-link token behavior (expiry, one-time use, hash storage, generic errors)
- Session security (idle timeout value, absolute timeout value, regeneration on login)
- Correction rules (what can be corrected on a finished match, what cannot)
- Locking behavior (how the lock version is incremented and checked)
- Audit log fields (what is recorded per entry)
- Livestream token behavior (expiry, one-time vs. reusable, stop conditions)

### MVP Scope vs. Code (doc -06 and -07)

Compare `docs/BarePitch-v2-06-mvp-scope-v1.0.md` and `docs/BarePitch-v2-07-functional-scope-guide-v1.0.md` against the implemented routes and services.

Flag any implemented feature that is marked "post-MVP" in the scope documents. These may have been built ahead of schedule (acceptable, but document the discrepancy). Flag any MVP-required feature that is not implemented (this is a gap that must be addressed before v1.0.0).

### Test Scenarios vs. Code (doc -10)

Compare `docs/BarePitch-v2-10-data-integrity-test-scenarios-v1.0.md` against the test suite in `tests/`.

Check:
- Every test scenario with an ID (e.g., MP-01, LU-01, SE-01) has a corresponding test in the test suite
- The test actually matches the scenario description
- Any scenario not yet covered by a test is flagged (prompt 08 will add tests — but this prompt documents the gap)

### Operational Details vs. Code (doc -15)

Compare `docs/BarePitch-v2-15-operational-and-implementation-details-v1.0.md` against the actual bootstrap, config, and deployment files.

Check:
- `.env.example` contains all required environment variables
- The session configuration matches the documented values
- The error handling approach matches what is documented
- Any operational guidance that does not match current implementation is updated

---

## New Documentation for v0.9.0 Work

Any behavior introduced or changed in this milestone that is not already covered by the docs requires a documentation update. Specifically:

### Statistics (from prompt 02)

If the route spec (`-08`) does not already document `GET /stats/players` and `GET /stats/team`:
- Add them to `docs/BarePitch-v2-08-route-api-specification-v1.0.md`
- Add the authorization requirement (any team role)
- Add the filter parameters (`season_id`, `phase_id`)

If the functional scope guide (`-07`) does not already mention basic statistics:
- Add a section describing what statistics are computed and from what source data

### i18n Foundation (from prompt 03)

If the implementation planning doc (`-11`) or operational doc (`-15`) does not mention the translation file structure:
- Add a brief note about `resources/lang/en.php` and the `__()` helper

### Security Headers (from prompt 04)

If the operational doc (`-15`) does not document the security headers being sent:
- Add a note listing `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, and the conditional `Strict-Transport-Security` header

---

## Documentation Update Rules

When updating a document:
1. Preserve the document's existing version number in the filename; update the `Last updated` date or version note inside the file if one exists
2. Make the minimum change needed to align the doc with the correct code behavior — do not refactor or restructure documents
3. Mark the change clearly so it can be reviewed (a comment in the commit message or a `<!-- UPDATED v0.9.0: ... -->` annotation near the changed text is acceptable)
4. Do not change the precedence order or the document numbering

---

## Alignment Summary Report

Create `DOCUMENTATION-ALIGNMENT.md` in the project root:

```markdown
# Documentation Alignment — v0.9.0

Date: [date]

## Summary

[One paragraph describing the overall alignment state]

## Findings

| # | Doc | Section | Finding | Action Taken |
|---|---|---|---|---|
| 1 | -01 schema | match_lineup table | playing_time_seconds column added in v0.6.0 but not documented | Added column to schema doc |
| 2 | -08 routes | /stats/* | Stats routes not in route spec | Added to route spec |
| 3 | -05 critical | session idle timeout | Code uses 30min; doc says 60min | Flagged for human review |
| ... | ... | ... | ... | ... |

## Implementation Gaps (MVP-required, not yet implemented)

| Feature | Doc Reference | Status |
|---|---|---|

## Deferred Features (Post-MVP, not implemented by design)

| Feature | Doc Reference | Milestone |
|---|---|---|

## Documents Updated

| Doc | What Changed |
|---|---|
```

---

# Out of Scope

- Rewriting or restructuring any document
- Changing the documentation precedence order
- Adding new documents not already in the `docs/` directory
- Documenting post-MVP features that have not been implemented
- Retroactively documenting decisions made in milestones before v0.9.0 that are not relevant to the current state

---

# Architectural Rules

- Documentation is updated to match code — not the other way around — when the code is correct
- When in doubt about which is correct, stop and flag for human review
- A documentation change that changes the intent of a behavior (not just its description) must be reviewed before merging
- No code is changed in this prompt to match a doc; code changes belong in prompts 02 through 06

---

# Acceptance Criteria

- `DOCUMENTATION-ALIGNMENT.md` exists in the project root
- Every finding in the report has an action taken (update, flag for review, or no action with reasoning)
- Every doc updated in this prompt has the correct final state that matches the implemented code
- The route spec (`-08`) documents `GET /stats/players` and `GET /stats/team`
- No `FAIL` finding is left without an action
- The full documentation set (docs -00 through -15) is in a state where a new developer could read it and understand the system as implemented

---

# Verification

1. Read each document listed in the Required Documentation section
2. For each normative statement, confirm the code matches or the finding is recorded
3. For each doc updated, re-read the updated section and confirm it accurately describes the code
4. Confirm `DOCUMENTATION-ALIGNMENT.md` exists and is complete
5. Confirm no document was changed in a way that alters the meaning of a security or authorization rule without human review

---

# Handoff Note

After this prompt, the documentation set accurately reflects the implemented system. `08-testing-and-verification.md` is the final prompt in this bundle. It implements the full test suite and performs the manual MVP acceptance walk-through. Any implementation gaps discovered during this documentation pass that were not fixed in earlier prompts must be addressed before the test suite can pass.

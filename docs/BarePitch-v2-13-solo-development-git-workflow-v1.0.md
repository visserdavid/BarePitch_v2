# BarePitch — Solo Development Git Workflow
Version 1.0 — April 2026

---

## 1. Purpose

This document defines the Git workflow for solo development on BarePitch.

It is the source of truth for:
- branch usage in solo development
- when to commit
- how to write commit messages
- when to push
- when to create issues
- how to document issues and fixes
- how documentation changes relate to code changes

This workflow is optimized for:
- one primary developer
- AI-assisted implementation
- low-overhead progress tracking
- safe incremental delivery

---

## 2. Workflow Goals

The Git workflow must support:
- small reversible changes
- clear historical reasoning
- explicit linkage between code and issue intent
- safe stopping points
- easy recovery after mistakes

The workflow must avoid:
- large unreviewable commits
- undocumented behavior changes
- unfinished mixed-purpose commits
- silent scope drift

---

## 3. Default Branch Model

Recommended default:
- `main` is the canonical branch

Solo-development rule:
- use short-lived feature or fix branches when the change is non-trivial
- direct commits to `main` are acceptable only for very small documentation-only or housekeeping changes

Recommended branch naming:
- `feature/<short-description>`
- `fix/<short-description>`
- `docs/<short-description>`
- `refactor/<short-description>`
- `chore/<short-description>`

Examples:
- `feature/live-match-goals`
- `fix/magic-link-rate-limit`
- `docs/authorization-matrix-update`

---

## 4. When to Create an Issue

Create an issue before starting work when:
- the change affects product behavior
- the change affects security
- the change affects data model or migrations
- the change affects permissions
- the change affects live match flow
- the change is expected to take more than one commit
- the change may require documentation updates

Issue creation is optional when:
- fixing a typo
- renaming files without semantic change
- making very small documentation-only formatting edits
- performing local cleanup with no behavioral impact

If unsure:
- create the issue

---

## 5. Issue Types

Recommended issue types:
- feature
- bug
- security
- refactor
- docs
- infrastructure

Each issue should have:
- title
- purpose
- problem statement
- expected outcome
- affected docs or modules
- acceptance criteria

---

## 6. Issue Template

Recommended issue structure:

### Title

Use:
- concise action plus scope

Examples:
- `Require recent auth for livestream token rotation`
- `Implement match period persistence`
- `Document stable bottom navigation rules`

### Body

Include:

1. Purpose
- what this issue is trying to achieve

2. Problem statement
- what is wrong, missing, risky, or unclear

3. Expected outcome
- what the finished state should be

4. Affected docs or modules
- which documentation files, code modules, or app areas are expected to be touched

5. Acceptance criteria
- concrete pass/fail outcomes

This structure should be placed directly in the issue body.

Recommended template:

```md
## Purpose

...

## Problem Statement

...

## Expected Outcome

...

## Affected Docs or Modules

...

## Acceptance Criteria

...
```

---

## 7. When to Commit

Commit when you reach a coherent checkpoint.

A commit must represent one of:
- one completed behavior change
- one completed schema change
- one completed permission change
- one completed documentation change
- one coherent refactor with preserved behavior

Do not wait too long.

Recommended commit frequency:
- multiple commits per issue if the issue is non-trivial
- one commit per logical step if the work spans architecture, schema, and UI

Commit immediately after:
- finishing a schema migration set
- finishing a critical service change
- finishing a documentation update that changes implementation guidance
- finishing a fix that restores correctness

Do not commit:
- broken intermediate work unless the branch is explicitly private and the commit message says so
- unrelated changes together
- mixed docs, schema, UI, and refactor changes without a single clear reason

---

## 8. Commit Quality Rules

Every commit must be:
- buildable or near-buildable
- logically coherent
- reviewable in isolation
- understandable from message and diff

Every commit should:
- keep tests passing where practical
- include related documentation updates when behavior changes

If a behavior change is committed without docs:
- the next commit must correct that immediately

---

## 9. Commit Message Rules

Preferred format:

- `Imperative summary`

Recommended:
- `Fix livestream token rotation audit flow`
- `Add match_period canonical schema`
- `Document stable bottom navigation model`

If using issues:
- include the issue number when available

Examples:
- `Fix #24: require recent auth for role changes`
- `Implement #31: add training attendance persistence`
- `Docs #42: formalize derived data ownership`

Commit messages must not be vague.

Avoid:
- `updates`
- `changes`
- `work in progress`
- `misc fixes`

If a temporary checkpoint commit is truly needed:
- use a branch-local commit
- squash or replace it before final merge into `main`

---

## 10. Documentation and Commit Coupling

A commit must update docs in the same commit when it changes:
- canonical schema
- permissions
- architecture boundaries
- route contract
- live match behavior
- security rules
- AI implementation rules

Allowed exception:
- code-first exploratory branch work before the design decision is accepted

But before merging to `main`:
- code and docs must match

---

## 11. When to Push

Push when:
- you finish a coherent checkpoint
- you want remote backup
- you want to switch devices or environments
- you want a safe restore point before risky refactor
- you want AI or tooling to continue from a stable branch state

Push immediately after:
- security-sensitive fixes
- schema changes
- corrected critical match logic
- issue completion

Do not leave important unpushed work locally for long.

Recommended rule:
- push at least once per active work session
- push after every significant completed commit chain

---

## 12. Branch Lifecycle

Recommended solo branch lifecycle:

1. Create issue
2. Create branch
3. Implement in small commits
4. Update docs
5. Verify behavior
6. Push branch
7. Merge into `main`
8. Delete branch
9. Close issue

If the branch diverges from scope:
- split the work
- create follow-up issue(s)
- do not silently expand the original issue

---

## 13. Merge Strategy

For solo development, prefer:
- squash merge for noisy multi-commit branches
- normal merge for branches where intermediate commit history is valuable

Recommended default:
- keep `main` readable
- preserve meaningful history

Use squash when:
- branch has many small fixup commits
- branch contains exploratory iteration

Use normal merge when:
- each commit is already clean and intentional
- preserving commit-by-commit reasoning is useful

---

## 14. Issue Closure Rules

Close an issue only when:
- implementation is complete for its stated scope
- docs are updated
- tests or verification are done to the expected level
- no known must-fix items remain inside the issue scope

Do not close an issue when:
- code exists but docs still disagree
- implementation is partial
- follow-up work is still required for stated acceptance criteria

If follow-up is needed but the core issue is done:
- close the original issue
- create a new issue for the remaining work
- cross-link them

Before closing the issue, post a solution comment using a similar structure to the original issue body.

Required solution comment sections:
- solution
- implemented changes
- affected docs or modules
- verification
- follow-up notes

Recommended solution comment template:

```md
## Solution

...

## Implemented Changes

...

## Affected Docs or Modules

...

## Verification

...

## Follow-up Notes

...
```

---

## 15. Bug Fix Workflow

For a bug:

1. Create issue unless the fix is trivial
2. Document:
- expected behavior
- actual behavior
- likely affected module
- reproduction steps if known
3. Implement narrowest safe fix
4. Add or update tests
5. Update docs if the docs were wrong or incomplete
6. Commit with explicit bug-focused message

Do not hide unrelated cleanup inside a bug fix unless it is required for the fix.

---

## 16. Security Fix Workflow

For a security-sensitive change:

1. Create issue
2. Mark clearly as security-related
3. Define risk and required mitigation
4. Implement minimal safe fix first
5. Update security docs in same change set
6. Push promptly after verification

Security changes should prefer:
- smaller commits
- clearer reasoning
- explicit documentation updates

---

## 17. Documentation-Only Workflow

Documentation-only issues or commits are valid when they:
- clarify implementation rules
- reduce ambiguity
- add missing policy
- align docs with already accepted behavior

For docs-only work:
- use `docs/...` branch if non-trivial
- commit in logically grouped chunks
- update the documentation map if precedence or ownership changes

---

## 18. Recommended Labels and Tracking

If using issue labels, recommended labels:
- `feature`
- `bug`
- `security`
- `docs`
- `schema`
- `permissions`
- `ui`
- `live-match`
- `post-mvp`

Recommended milestone use:
- MVP
- post-MVP foundation
- hardening

If release versions are tracked through milestones:
- the `MVP` milestone maps to release `v1.0.0`

---

## 19. Daily Working Rhythm

Recommended solo rhythm:

1. Start from current docs and open issue list
2. Pick one issue or one tightly related pair
3. Create branch
4. Implement in small commits
5. Update docs as soon as the decision is real
6. Verify
7. Push
8. Merge or leave branch in stable state

Avoid parallel half-finished branches unless necessary.

For solo work, throughput improves when the number of open active branches stays low.

---

## 20. AI-Assisted Git Discipline

When AI is used to implement changes:
- the issue scope must be explicit
- the relevant canonical docs must be named
- the AI must not silently invent behavior outside documented scope
- doc updates must be included before issue closure

If AI-generated work reveals a documentation gap:
- update docs first or in the same change set
- do not leave the gap implicit

If the issue is completed:
- the final issue comment must document the applied solution using the approved solution-comment template

---

## 21. Minimum Standard Before Merge to `main`

Before merging to `main`, ensure:
- issue scope is satisfied
- commit history is understandable
- docs and code agree
- security rules are still respected
- no temporary auth or debug shortcuts are left enabled
- any migration is intentional and documented

If the merge is part of a formal release:
- the release version must match the documented project versioning policy

---

## 22. Summary

The solo BarePitch Git workflow is:
- issue-driven for meaningful work
- branch-based for non-trivial changes
- commit-small and commit-coherent
- push regularly
- update docs whenever behavior or rules change
- close issues only when implementation and documentation both align

The main rule is simple:

If a future you cannot understand why a change happened from the issue, commit history, and docs, the workflow was not followed well enough.

---

## End

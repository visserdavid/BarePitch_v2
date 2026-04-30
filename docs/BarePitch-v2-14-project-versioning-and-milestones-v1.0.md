# BarePitch — Project Versioning and Milestones
Version 1.0 — April 2026

---

## 1. Purpose

This document defines the project versioning scheme for BarePitch.

It is the source of truth for:
- release version format
- milestone-to-version mapping
- MVP release version
- when version numbers change
- how issues, releases, and milestones relate

---

## 2. Core Rule

BarePitch uses milestone-based semantic versioning.

Format:
- `vMAJOR.MINOR.PATCH`

Examples:
- `v0.1.0`
- `v0.5.0`
- `v1.0.0`
- `v1.1.0`
- `v1.1.1`

---

## 3. Meaning of Each Part

### Major

Increase `MAJOR` when:
- a major product milestone is reached
- release scope meaningfully expands
- compatibility or expected workflows change significantly

### Minor

Increase `MINOR` when:
- a meaningful new feature set is added after a major milestone
- a post-milestone product increment is released
- behavior expands without being treated as a patch-only change

### Patch

Increase `PATCH` when:
- bugs are fixed
- security issues are fixed
- documentation-aligned implementation corrections are released
- no new milestone-level scope is introduced

---

## 4. Milestone Mapping

Recommended milestone mapping:

- `v0.x.x` = pre-MVP development
- `v1.0.0` = MVP release
- `v1.x.x` = post-MVP improvements within the same major product generation
- `v2.0.0` = next major product generation if the product meaningfully changes

---

## 5. MVP Rule

The BarePitch MVP release version must be:
- `v1.0.0`

This is the first version that claims:
- usable end-to-end core match flow
- no required developer intervention for normal use
- documented security baseline
- documented operational baseline

Anything before MVP must remain below `v1.0.0`.

---

## 6. Pre-MVP Version Guidance

Recommended pre-MVP version progression:

- `v0.1.0` = first working vertical slice
- `v0.2.0` = stable authentication and team context
- `v0.3.0` = stable player and match creation flow
- `v0.4.0` = stable match preparation
- `v0.5.0` = stable live match core
- `v0.6.0` = substitutions and cards
- `v0.7.0` = extra time, penalties, shootout
- `v0.8.0` = livestream and corrections
- `v0.9.0` = hardening and MVP candidate
- `v1.0.0` = MVP release

These version numbers are guidance, not a forced one-to-one release requirement.

The important rule is:
- do not call any release `v1.0.0` until MVP criteria are truly met

---

## 7. Release Naming Rules

Every formal release should have:
- version number
- release date
- linked issue or milestone summary
- release notes

Recommended release note structure:

1. Scope
2. Included changes
3. Data or migration impact
4. Security impact
5. Verification summary
6. Known follow-up issues

---

## 8. When to Bump Versions

### Bump Patch

Use patch bumps for:
- `v1.0.0 -> v1.0.1`
- `v1.0.1 -> v1.0.2`

Typical reasons:
- bug fixes
- small security improvements
- production corrections

### Bump Minor

Use minor bumps for:
- `v1.0.0 -> v1.1.0`
- `v1.1.0 -> v1.2.0`

Typical reasons:
- meaningful feature expansion
- new modules moving from post-MVP into release

### Bump Major

Use major bumps for:
- `v1.x.x -> v2.0.0`

Typical reasons:
- major product generation shift
- significant workflow or compatibility change

---

## 9. Issue and Milestone Relationship

Project management should align as follows:

- issues describe work
- milestones group issues
- versions label releases

Recommended mapping:
- one GitHub milestone may correspond to one planned release version
- `MVP` milestone corresponds to `v1.0.0`

Issue bodies do not need version numbers unless the issue is explicitly release-scoped.

---

## 10. Git and Tagging Rules

When a release is formalized:
- create a Git tag for the release version
- use the exact release number as tag name

Examples:
- `v0.9.0`
- `v1.0.0`
- `v1.0.1`

Do not tag:
- unstable local checkpoints
- incomplete branch states

---

## 11. Documentation Update Rules

When version milestone meaning changes:
- update this document
- update MVP or implementation docs if milestone expectations changed
- update release planning docs if version targets changed

When the project reaches MVP:
- documentation referring to "the MVP release" should align with `v1.0.0`

---

## 12. AI Implementation Notes

When AI generates release-related artifacts:
- treat `v1.0.0` as the MVP release
- do not invent alternative versioning formats
- do not label pre-MVP builds as `v1.0.0`

---

## End

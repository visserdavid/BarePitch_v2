# Release Notes — v1.0.0

# Purpose
Produce the official release notes document for the BarePitch v1.0.0 MVP release. This prompt is executed after `04-release-verification-checklist.md` is fully signed off and the `v1.0.0` tag has been created. The agent must fill in each section based on the actual verified state of the codebase — do not write placeholder text or speculative content. Every section must reflect what is true of the tagged commit.

---

# Required Context
See `01-shared-context.md`. Read the complete pre-release verification report (produced in `02-pre-release-verification.md`) and the signed-off checklist (produced in `04-release-verification-checklist.md`) before writing these notes. The release notes must be consistent with both documents.

---

# Required Documentation
- `docs/BarePitch-v2-06-mvp-scope-v1.0.md` — authoritative MVP include/exclude list
- `docs/BarePitch-v2-14-project-versioning-and-milestones-v1.0.md` — versioning policy
- `docs/BarePitch-v2-13-solo-development-git-workflow-v1.0.md` — release tagging rules
- The completed `04-release-verification-checklist.md`
- The `git log` from the beginning of the project to the `v1.0.0` tag

---

# Scope

## Output File

Write the release notes to: `docs/release-notes-v1.0.0.md`

The file must be complete and self-contained. A reader with no prior context should be able to understand what BarePitch v1.0.0 is, what it includes, what was done to verify it, and what is known to need attention after release.

---

## Section Instructions

Write each section as instructed below. Replace every `[FILL IN: ...]` directive with actual content derived from the codebase, the verification report, and the checklist. Do not leave any `[FILL IN: ...]` directive in the final output file.

---

### Section 1 — Scope

**Instructions**: Describe what BarePitch v1.0.0 is and what it is designed to do. State the target user (coaches managing football teams on shared hosting). State the delivery model (PHP/MySQL, no framework, shared hosting). State what the MVP scope covers at a high level. Do not repeat the detailed include/exclude lists — summarize in 3–5 sentences.

```markdown
## Scope

[FILL IN: 3–5 sentence description of what BarePitch v1.0.0 is, what it does,
who it is for, and what is in scope at a high level.]
```

---

### Section 2 — Included Changes

**Instructions**: List every functional module included in v1.0.0. For each module, write one sentence describing what it does. Base this list on `docs/BarePitch-v2-06-mvp-scope-v1.0.md` and confirm against the verified codebase. If a module listed in the MVP include scope was not fully implemented and was treated as a known follow-up issue, do not list it here — list it in Section 6.

Also include a subsection listing the milestone path: v0.1.0 through v1.0.0, with one sentence describing what each milestone contributed.

```markdown
## Included Changes

### Functional Modules

[FILL IN: Bullet list of included modules, each with a one-sentence description.
Example format:
- **Authentication**: One-time magic-link login with hashed token storage and secure session management.
- **Team context**: Role-based access with explicit team selection for users who have multiple teams.
- ... (continue for all verified modules)
]

### Milestone History

[FILL IN: Ordered list of milestones v0.1.0 through v1.0.0, each with one sentence
describing its primary contribution to the MVP.]
```

---

### Section 3 — Data and Migration Impact

**Instructions**: Describe the database schema as delivered. List the migration files that must be run to install v1.0.0 from scratch. State whether there is an upgrade path from any prior version (there may not be, since this is the first public release). If any schema change was made during the v1.0.0 fixes (in `03-release-blocking-fixes.md`), list those migration files explicitly.

```markdown
## Data and Migration Impact

### Fresh Install

[FILL IN: Steps to install the schema on a fresh database. List migration files
in order. State the seed step if applicable.]

### Upgrade from Prior Versions

[FILL IN: State whether an upgrade path from v0.9.0 or earlier exists. If this
is the first public release and no upgrade path is supported, state that explicitly.
If migration files were added during v1.0.0 fixes, list them.]

### Schema Summary

[FILL IN: Brief description of the core tables in the installed schema and
what each stores. This should match docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md.]
```

---

### Section 4 — Security Impact

**Instructions**: Summarize the security measures in place for v1.0.0. Cover each of the security baseline items from `01-shared-context.md`. For each item, state the current implementation approach. Do not reveal internal implementation details that would aid an attacker — write at a policy/behavior level, not at a code level.

```markdown
## Security Impact

[FILL IN: Bullet list of security measures implemented in v1.0.0. Cover each of:
- Magic-link token handling (storage method, one-time use, expiry)
- Session security (settings, regeneration on login, idle/absolute lifetime)
- CSRF protection (coverage)
- Server-side authorization (policy layer, UI hiding is not sufficient)
- SQL injection prevention (prepared statements)
- Error handling (no stack traces in production)
- Rate limiting (which endpoints)
- Public token pages (cache and indexing headers)
- Developer bypass policy (APP_ENV=local only)
- HTTPS and HSTS (if applicable)
]
```

---

### Section 5 — Verification Summary

**Instructions**: Summarize what was verified before tagging. Reference the pre-release verification report and the signed-off checklist. For each verification step from `02-pre-release-verification.md`, state the result (pass/fail and key metric). Include the automated test count. Include the acceptance scenario result. Include the authorization spot-check result. Include the token leak test result. Include the error exposure test result.

```markdown
## Verification Summary

[FILL IN: For each verification step (Steps 1–8 from 02-pre-release-verification.md),
write one bullet point with the step name and the result. Example:
- **Clean schema install**: PASS — all N migration files ran without errors.
- **PHP syntax check**: PASS — zero syntax errors across app/, public/, database/.
- **Automated tests**: PASS — N tests, N passed, 0 failed, 0 errors.
- **MVP acceptance scenario**: PASS — all 19 sub-steps completed without errors.
- **Authorization spot-check**: PASS — all 11 checks matched the authorization matrix.
- **Token leak test**: PASS — expired, consumed, and non-existent token responses are indistinguishable.
- **Stack trace test**: PASS — no internal detail exposed in any error condition.
- **Documentation alignment**: PASS — N discrepancies found, all classified; 0 blockers remaining.
]

### Blockers Found and Resolved

[FILL IN: If any release-blocking issues were found during verification and fixed via
03-release-blocking-fixes.md, list each blocker here with: the blocker criterion number,
a one-sentence description of the issue, and a one-sentence description of the fix applied.
If no blockers were found, state: "No release-blocking issues were found during verification."]

### Residual Risk

[FILL IN: List any areas where the verification was less thorough than ideal, or where
a risk remains after release (e.g., a race condition that is unlikely but not fully tested,
a browser compatibility gap found at smartphone viewport, a rate-limit threshold that is
set conservatively and may need tuning). If no residual risk was identified, state that.]
```

---

### Section 6 — Known Follow-Up Issues

**Instructions**: List every known issue, gap, or improvement that was intentionally deferred from v1.0.0. This includes: items in the MVP exclude list that came up during development, items in the MVP include list that turned out to be partially implemented (if any), bugs found during verification that are not release-blocking, and any UI/UX issues found during the smartphone viewport test. For each issue, write: a short title, a one-sentence description, and a severity classification (low / medium / high — but note that high-severity items should have been fixed before tagging).

```markdown
## Known Follow-Up Issues

[FILL IN: Bullet list of known follow-up issues. Example format:
- **[Low] Smartphone viewport — lineup grid overflow**: The lineup grid at 390px viewport
  width requires horizontal scroll. Functional but not ideal; CSS grid refinement deferred
  to post-MVP.
- **[Low] Basic statistics — playing time precision**: Playing time is tracked in seconds
  but displayed rounded to minutes. Sub-minute precision deferred.
- ... (continue for all known issues)
]

### Out of Scope (Explicitly Deferred)

[FILL IN: Brief list of the MVP exclude items that are explicitly deferred to future versions.
This does not need to be exhaustive — reference docs/BarePitch-v2-06-mvp-scope-v1.0.md
for the complete list. Highlight the most likely next-priority items.]
```

---

## Final Checklist Before Writing the File

Before writing the output file, confirm:

1. The `v1.0.0` tag exists in the repository (`git tag | grep v1.0.0`).
2. The pre-release verification report is complete and every step is PASS.
3. The `04-release-verification-checklist.md` is fully signed off.
4. No `[FILL IN: ...]` directives remain in the output.
5. Every statement in the release notes is consistent with the verified codebase state.

---

# Out of Scope

- Writing speculative content about planned future features
- Describing features that are in the MVP exclude list as if they were included
- Leaving any `[FILL IN: ...]` directive in the output file
- Producing release notes before the `v1.0.0` tag is created

---

# Architectural Rules

- The release notes are a documentation artifact, not production code. They do not need to follow the Controller/Service/Repository layer rules.
- The release notes must not contain internal implementation details (file paths, class names, SQL query text) unless they are necessary to explain a migration step.
- The release notes are written in past tense for completed items ("Authentication was implemented using...") and present tense for current state ("The schema installs via...").

---

# Acceptance Criteria

- The file `docs/release-notes-v1.0.0.md` exists and is committed to the repository.
- All six sections are present and fully filled in.
- No `[FILL IN: ...]` directive remains.
- Every statement is consistent with the verified codebase state and the signed-off checklist.
- The known follow-up issues section is honest — it does not hide issues that were observed during verification.

---

# Verification

After writing the file:
1. Read through the entire document and confirm no section is empty or contains placeholder text.
2. Cross-check Section 2 (included modules) against the signed-off checklist Section 4 (acceptance scenario).
3. Cross-check Section 5 (verification summary) against the pre-release verification report.
4. Cross-check Section 4 (security impact) against the signed-off checklist Section 8.
5. Confirm the file is committed: `git log --oneline -1 -- docs/release-notes-v1.0.0.md`

---

# Handoff Note

This is the final prompt in the v1.0.0 bundle. After this file is written, committed, and verified, the BarePitch v1.0.0 MVP release is complete. The `v1.0.0` tag marks the first publicly usable release. All future work proceeds on a new version branch per the git workflow in `docs/BarePitch-v2-13-solo-development-git-workflow-v1.0.md`.

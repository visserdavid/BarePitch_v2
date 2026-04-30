# BarePitch — Authorization Matrix
Version 1.0 — April 2026

---

## 1. Purpose

This document defines the canonical authorization matrix for BarePitch.

It is the source of truth for:
- role permissions
- resource-action rules
- team-scope restrictions
- match-state restrictions
- recent-authentication requirements
- audit expectations on sensitive actions

If prose in another document conflicts with this matrix, this document wins.

---

## 2. Roles

Roles covered by this matrix:
- `administrator`
- `coach`
- `trainer`
- `team_manager`
- `authenticated_no_role`
- `public`

Notes:
- `administrator` is global
- team-level roles apply only within teams where assigned
- users may have multiple team-level roles
- permissions are cumulative within a team context

---

## 3. Interpretation Rules

- `Allow` means permission is granted if all documented conditions are met.
- `Deny` means permission is not granted.
- Team-scoped resources must be loaded through accessible team scope where possible.
- Lock ownership never replaces authorization.
- `Recent auth` means the user must have a recently authenticated session or explicit step-up confirmation.

---

## 4. Matrix

| Resource | Action | Administrator | Coach | Trainer | Team Manager | Auth No Role | Public | Conditions | Recent Auth | Audited |
|---|---|---|---|---|---|---|---|---|---|---|
| club | create | Allow | Deny | Deny | Deny | Deny | Deny | global only | No | Yes |
| club | update | Allow | Deny | Deny | Deny | Deny | Deny | global only | Yes | Yes |
| season | create | Allow | Deny | Deny | Deny | Deny | Deny | global only | No | Yes |
| season | update | Allow | Deny | Deny | Deny | Deny | Deny | global only | Yes | Yes |
| phase | create | Allow | Deny | Deny | Deny | Deny | Deny | within season | No | Yes |
| phase | update | Allow | Deny | Deny | Deny | Deny | Deny | within season | Yes | Yes |
| team | view | Allow | Allow | Allow | Allow | Deny | Deny | same team unless admin | No | No |
| team | create | Allow | Deny | Deny | Deny | Deny | Deny | global only | Yes | Yes |
| team | update | Allow | Deny | Deny | Limited Allow | Deny | Deny | team manager only for allowed fields | Yes | Yes |
| user | create | Allow | Deny | Deny | Deny | Deny | Deny | global only | Yes | Yes |
| user | update | Allow | Deny | Deny | Deny | Deny | Deny | global only | Yes | Yes |
| role assignment | view | Allow | Deny | Deny | Allow | Deny | Deny | same team unless admin | No | No |
| role assignment | create | Allow | Deny | Deny | Allow | Deny | Deny | team manager cannot assign admin | Yes | Yes |
| role assignment | remove | Allow | Deny | Deny | Allow | Deny | Deny | same team unless admin | Yes | Yes |
| player | view | Allow | Allow | Allow | Allow | Deny | Deny | same team unless admin | No | No |
| player | create | Allow | Deny | Deny | Allow | Deny | Deny | active team required | No | No |
| player | update | Allow | Deny | Deny | Allow | Deny | Deny | same team unless admin | No | No |
| player | deactivate | Allow | Deny | Deny | Allow | Deny | Deny | soft deactivate only | Yes | Yes |
| guest player | create external | Allow | Allow | Deny | Allow | Deny | Deny | active team or season context | No | No |
| training | view | Allow | Allow | Allow | Allow | Deny | Deny | same team unless admin | No | No |
| training | create | Allow | Deny | Allow | Allow | Deny | Deny | same team unless admin | No | No |
| training | update | Allow | Deny | Allow | Allow | Deny | Deny | same team unless admin | No | No |
| training | cancel | Allow | Deny | Allow | Allow | Deny | Deny | same team unless admin | No | Yes |
| training attendance | write | Allow | Deny | Allow | Allow | Deny | Deny | same team unless admin | No | No |
| match | view | Allow | Allow | Allow | Allow | Deny | Deny | same team unless admin | No | No |
| match | create | Allow | Allow | Deny | Deny | Deny | Deny | active team required | No | No |
| match | update | Allow | Allow | Deny | Deny | Deny | Deny | only planned or prepared unless correction route | No | No |
| match | delete | Allow | Allow | Deny | Deny | Deny | Deny | no live events and policy allows delete | Yes | Yes |
| match attendance | write | Allow | Allow | Deny | Allow | Deny | Deny | planned or prepared | No | No |
| match guest selection | write | Allow | Allow | Deny | Deny | Deny | Deny | planned or prepared | No | No |
| match formation | write | Allow | Allow | Deny | Deny | Deny | Deny | planned or prepared | No | No |
| match lineup | write | Allow | Allow | Deny | Deny | Deny | Deny | planned or prepared | No | No |
| match prepare | confirm | Allow | Allow | Deny | Deny | Deny | Deny | validation rules must pass | No | No |
| match lock | acquire | Allow | Allow | Deny | Allow | Deny | Deny | must also have edit permission for current match state | No | No |
| match lock | refresh | Allow | Allow | Deny | Allow | Deny | Deny | must own lock and still have edit permission | No | No |
| match lock | release | Allow | Allow | Deny | Allow | Deny | Deny | owner or admin | No | No |
| live match | open | Allow | Allow | Deny | Deny | Deny | Deny | prepared or active | No | No |
| match start | execute | Allow | Allow | Deny | Deny | Deny | Deny | prepared + lock + validations | No | Yes |
| period transition | execute | Allow | Allow | Deny | Deny | Deny | Deny | active + lock + valid phase | No | Yes |
| live event | create | Allow | Allow | Deny | Deny | Deny | Deny | active + lock + valid state | No | No |
| substitution | create | Allow | Allow | Deny | Deny | Deny | Deny | active + lock + valid lineup state | No | No |
| shootout attempt | create | Allow | Allow | Deny | Deny | Deny | Deny | active shootout + lock | No | No |
| match finish | execute | Allow | Allow | Deny | Deny | Deny | Deny | active + valid finish point + lock | No | Yes |
| livestream | public view | Deny | Deny | Deny | Deny | Deny | Allow | valid active token required | No | No |
| livestream | stop | Allow | Allow | Deny | Deny | Deny | Deny | same team unless admin | No | Yes |
| livestream | rotate token | Allow | Allow | Deny | Deny | Deny | Deny | same team unless admin | Yes | Yes |
| finished correction | view UI | Allow | Allow | Deny | Deny | Deny | Deny | finished match only | No | No |
| finished correction | event update | Allow | Allow | Deny | Deny | Deny | Deny | finished + lock | No | Yes |
| finished correction | substitution update | Allow | Allow | Deny | Deny | Deny | Deny | finished + lock | No | Yes |
| finished correction | shootout update | Allow | Allow | Deny | Deny | Deny | Deny | finished + lock | No | Yes |
| ratings | view | Allow | Allow | Deny | Deny | Deny | Deny | post-match, post-MVP | No | No |
| ratings | write | Allow | Allow | Deny | Deny | Deny | Deny | post-match, post-MVP | No | No |
| statistics | view | Allow | Allow | Allow | Allow | Deny | Deny | same team unless admin | No | No |
| settings.language | update self | Allow | Allow | Allow | Allow | Allow | Deny | authenticated only | No | No |
| formations | manage | Allow | Deny | Deny | Conditional Allow | Deny | Deny | if policy allows for team manager | No | No |
| audit log | view | Allow | Deny | Deny | Deny | Deny | Deny | admin only unless future policy expands | Yes | Yes |
| audit log | export | Allow | Deny | Deny | Deny | Deny | Deny | admin only | Yes | Yes |

---

## 5. High-Impact Actions Requiring Recent Authentication

Recent authentication is required for:
- role assignment changes
- role removal
- club updates
- team creation
- user creation
- livestream token rotation
- audit log export
- any future impersonation or support tooling

Recent authentication is not required for:
- routine live-match event entry
- routine match preparation
- routine player edits

---

## 6. Public Access Rules

Public users may only:
- view livestream HTML when token is valid
- poll livestream JSON when token is valid
- access login request UI
- submit login request
- consume login token

Public users may never:
- inspect audit data
- view team dashboards
- access corrections
- access any authenticated route by guessed IDs

---

## 7. AI Implementation Notes

When generating authorization code:
- enforce authorization in server-side policy or service checks
- never rely on hidden buttons or disabled UI
- prefer scoped lookup queries
- do not invent role powers beyond this matrix

If a route or action is missing from this matrix:
- do not assume permission
- add the action to this document before implementation

---

## End

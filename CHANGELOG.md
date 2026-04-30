# Changelog

All notable changes to BarePitch v2 will be documented in this file.

BarePitch uses milestone-based semantic versioning:

- `v0.x.x` = pre-MVP development
- `v1.0.0` = MVP release
- `v1.x.x` = post-MVP improvements
- `v2.0.0` = future major product generation

## [Unreleased]

### Added

- Project documentation set in `docs/`.
- MVP milestone implementation prompts in `prompts/mvp-milestones/`.
- GitHub-facing `README.md`.
- GitHub-facing `CHANGELOG.md`.
- Initial project folder scaffold for the documented PHP/MySQL architecture.
- GitHub issue templates and pull request template.
- Repository hygiene files: `.gitignore`, `.editorconfig`, and `.env.example`.

### Notes

- The project is not yet at MVP release.
- The MVP release version remains reserved for `v1.0.0`.

## [v1.0.0] - Planned

### Scope

The MVP release is planned to include:

- Authentication and team context
- Administrator setup for club, season, phase, team, users, and roles
- Player management
- Guest players
- Match creation
- Match preparation
- Formation and lineup grid
- Live match management
- Goal registration
- Substitutions
- Cards and red-card restrictions
- Penalties during match
- Extra time
- Penalty shootout
- Match finish and basic summary
- Public livestream
- Finished match corrections
- Audit logging
- Locking and concurrency protection
- Basic statistics
- Basic internationalization support
- MVP security baseline

### Release Criteria

The MVP may be released as `v1.0.0` only when:

- A coach can run a complete match in BarePitch.
- An administrator can set up the minimum club, season, phase, team, and role structure.
- Data survives refresh.
- Score remains consistent.
- Lineup state remains consistent.
- Red-card rules work.
- Corrections work and are audited.
- Livestream works.
- Authorization works.
- No normal workflow requires manual database intervention.

## [v0.9.0] - Planned

### Scope

- Hardening and MVP candidate.
- Security review.
- Authorization review.
- CSRF review.
- Public token endpoint review.
- Error handling review.
- Full MVP acceptance verification.

## [v0.8.0] - Planned

### Scope

- Livestream.
- Finished match corrections.
- Audit logging.
- Match edit locking and conflict prevention.

## [v0.7.0] - Planned

### Scope

- Penalties during match.
- Extra time.
- Penalty shootout.
- Shootout ending rules.

## [v0.6.0] - Planned

### Scope

- Substitutions.
- Playing time tracking.
- Yellow cards.
- Red cards.
- Red-card restrictions.

## [v0.5.0] - Planned

### Scope

- Live match core.
- Start match.
- Regular-time period controls.
- Goal registration.
- Timeline.
- Finish match.

## [v0.4.0] - Planned

### Scope

- Match preparation.
- Attendance.
- Guest player selection.
- Formation selection.
- Lineup grid.
- Bench assignment.

## [v0.3.0] - Planned

### Scope

- Player management.
- External guest player creation.
- Match creation.
- Match list and detail views.
- Minimum administrative setup flow.

## [v0.2.0] - Planned

### Scope

- Authentication.
- Magic-link login.
- Secure sessions.
- Team context.
- Role loading.
- Server-side permission checks.

## [v0.1.0] - Planned

### Scope

- First working vertical slice.
- Project foundation.
- Database foundation.
- Seed data.
- Minimal team, player, match, lineup, live goal, finish, and summary flow.

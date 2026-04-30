# BarePitch v2

BarePitch is a lightweight football match management app for coaches.

The MVP goal is simple: a coach should be able to manage a complete match flow from preparation to completion without manual database edits or developer intervention.

## MVP Scope

BarePitch MVP focuses on the core match workflow:

- Authentication and team context
- Club, season, phase, team, user, and role setup
- Player management
- Guest players
- Match creation
- Match preparation
- Formation and lineup grid
- Live match management
- Goals, assists, penalties, substitutions, and cards
- Extra time and penalty shootout
- Public livestream
- Finished match corrections
- Audit logging
- Locking and concurrency protection
- Basic statistics
- Basic internationalization support

The MVP release version is `v1.0.0`.

## Explicit Non-Goals for MVP

The MVP does not include:

- Advanced analytics
- Heatmaps
- Tactical whiteboards
- AI recommendations
- Push notifications
- Chat or social features
- External league integrations
- Automatic fixture imports
- Advanced dashboard widgets
- Parent portals
- Player photos
- Advanced media uploads
- Realtime websocket infrastructure
- Mobile apps
- Offline-first architecture
- Advanced exports
- Tournament systems
- Training session management
- Training attendance workflows
- Post-match ratings

## Technical Constraints

BarePitch is designed for shared hosting and intentionally uses a conservative stack:

- PHP
- MySQL
- Plain CSS
- Vanilla JavaScript

The project must not require:

- Node.js
- Laravel
- Docker
- Redis
- Frontend frameworks
- Build pipelines

## Documentation

The project documentation lives in [`docs/`](docs/).

Start with:

- [`docs/BarePitch-v2-00-documentation-map.md`](docs/BarePitch-v2-00-documentation-map.md)
- [`docs/BarePitch-v2-06-mvp-scope-v1.0.md`](docs/BarePitch-v2-06-mvp-scope-v1.0.md)
- [`docs/BarePitch-v2-11-implementation-planning-v1.0.md`](docs/BarePitch-v2-11-implementation-planning-v1.0.md)
- [`docs/BarePitch-v2-12-ai-implementation-rules-v1.0.md`](docs/BarePitch-v2-12-ai-implementation-rules-v1.0.md)
- [`docs/BarePitch-v2-14-project-versioning-and-milestones-v1.0.md`](docs/BarePitch-v2-14-project-versioning-and-milestones-v1.0.md)

When documents conflict, follow the precedence rules in the documentation map.

## Project Structure

The repository is organized around the documented PHP/MySQL architecture:

```text
app/
  Config/              Application configuration and route definitions
  Core/                Router, request, response, database, and framework-lite primitives
  Domain/              Enums, constants, value objects, and calculators
  Http/Controllers/    HTTP orchestration
  Http/Requests/       Request validation
  Policies/            Authorization logic
  Repositories/        Database persistence access
  Services/            Business logic and transactional use cases
  Views/               Templates and presentational fragments
database/
  migrations/          Ordered schema changes
  seeds/               Local/test seed data
public/
  css/                 Plain CSS
  js/                  Vanilla JavaScript
  assets/              Static public assets
storage/
  cache/               Runtime cache files
  logs/                Runtime logs
  sessions/            Runtime session storage if file-backed sessions are used
tests/
  Feature/             User-flow and route-level tests
  Integration/         Cross-layer persistence and service tests
  Unit/                Isolated validator, policy, and domain tests
```

`public/` should be the web server document root where hosting allows it.

## AI Milestone Prompts

Executable implementation prompts are available in [`prompts/mvp-milestones/`](prompts/mvp-milestones/).

They split the MVP path into versioned milestones from `v0.1.0` through `v1.0.0`.

## Development Status

BarePitch v2 is currently in documentation and pre-MVP planning.

Do not label any build as `v1.0.0` until the MVP completion criteria in the docs are satisfied.

## Security Baseline

The MVP must include:

- Prepared SQL statements
- CSRF protection
- Output escaping
- Server-side authorization
- Secure sessions
- One-time magic links
- Expiring authentication tokens
- Hashed token storage
- Login and public endpoint rate limiting
- Safe public token failure responses
- No-store and noindex protections for livestream pages
- Production HTTPS and HSTS expectations

## Versioning

BarePitch uses milestone-based semantic versioning:

- `v0.x.x` for pre-MVP development
- `v1.0.0` for the MVP release
- `v1.x.x` for post-MVP improvements
- `v2.0.0` for a future major product generation

See [`CHANGELOG.md`](CHANGELOG.md) for release history.

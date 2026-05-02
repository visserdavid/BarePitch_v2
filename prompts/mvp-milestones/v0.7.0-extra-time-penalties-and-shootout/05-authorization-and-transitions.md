# Authorization and State Machine Review — v0.7.0

# Purpose
Cross-cutting review of authorization, CSRF, and state machine transitions for all v0.7.0 routes. Verify the normal score and shootout score are always separate, and that sent-off restrictions apply consistently.

# Required Context
See `01-shared-context.md`. All prior prompts (02–04) complete.

# Required Documentation
- `docs/BarePitch-v2-02-authorization-matrix-v1.0.md`
- `docs/BarePitch-v2-05-critical-behavior-specifications-v1.0.md`

# Scope

## Route audit

| Route | CSRF | Auth | State check |
|---|---|---|---|
| `POST /matches/{id}/events/penalty` | ✓ | coach/admin | active |
| `POST /matches/{id}/periods/start-extra-time` | ✓ | coach/admin | regular time ended |
| `POST /matches/{id}/periods/end-extra-time-first-half` | ✓ | coach/admin | extra_time_first_half running |
| `POST /matches/{id}/periods/start-extra-time-second-half` | ✓ | coach/admin | extra_time_first_half ended |
| `POST /matches/{id}/periods/end-extra-time-second-half` | ✓ | coach/admin | extra_time_second_half running |
| `POST /matches/{id}/shootout/start` | ✓ | coach/admin | regular or extra time ended |
| `POST /matches/{id}/shootout/attempts` | ✓ | coach/admin | shootout started, not finished |
| `POST /matches/{id}/shootout/finish` | ✓ | coach/admin | shootout started |

For any missing check: add it to the controller method.

## Score separation verification

- Grep for any SQL that joins `match_events` with `match_shootout_attempts` in a combined score
- Grep for any UPDATE that sets a score column in response to a shootout attempt
- Verify `ScoreService::calculate()` does NOT query `match_shootout_attempts`
- Verify `ShootoutService::getShootoutScore()` does NOT query `match_events`

Both must be zero findings.

## Sent-off restriction consistency

Verify that `getEligiblePenaltyTakers()` is called in:
1. `PenaltyEventService::register()` (in-match penalty taker validation)
2. `ShootoutService::recordAttempt()` (shootout taker validation for own team)

Both calls must use the same repository method, ensuring consistent restriction.

## State machine boundary tests

Manually verify (or write inline assertions):
- Attempting to start shootout during active first half → rejected
- Attempting to start extra time when match is `planned` → rejected
- Attempting to record shootout attempt before shootout is started → rejected
- Attempting to record penalty event after match is `finished` → rejected

# Out of Scope
- Implementing new penalty, extra-time, or shootout features beyond fixing missing checks found in this review.
- Livestream, finished-match corrections, audit logging, or hardening work from later milestones.
- Changing the documented match state machine.

# Architectural Rules
- Authorization and CSRF checks must run before any state-changing Service call.
- State transitions must be server-authoritative and must reject invalid prior states.
- Normal match score and penalty shootout score must remain separate derived values.
- Sent-off player restrictions must be enforced by shared domain/service logic, not by UI filtering alone.

# Acceptance Criteria
- Every v0.7.0 POST route has CSRF and policy check verified
- Normal score and shootout score computationally isolated
- `sent_off` restriction applied in both in-match penalty and shootout taker selection
- Invalid state transitions (e.g., shootout during first half) return safe errors

# Verification
- PHP syntax check all files
- Manual test: attempt `POST /matches/{id}/shootout/start` during active first half → verify rejection
- Grep `ScoreService` for any reference to `match_shootout_attempts` → must find zero
- Grep `ShootoutService::getShootoutScore` for any reference to `match_events` → must find zero

# Handoff Note
`06-testing-and-verification.md` adds automated tests covering SC-03 through SC-05, PS-01 through PS-05, and the key edge cases.

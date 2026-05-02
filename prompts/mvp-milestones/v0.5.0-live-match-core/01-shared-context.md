# Shared Context — v0.5.0 Live Match Core

# Purpose
Reusable reference for all prompts in the v0.5.0 bundle. Read this before executing any subsequent prompt in this directory.

---

## Milestone Identity

**Version**: v0.5.0  
**Name**: Live Match Core  
**Goal**: Allow a coach to run a complete regular-time match: start, register goals (own/opponent with optional assist and zone), manage period transitions, finish, and view the result.

---

## Technical Stack

- PHP, MySQL, plain CSS, vanilla JS. No framework, no build pipeline.

---

## Architecture

Route → Controller → Validator → Policy → Service → Repository → View. Same layer rules as all prior milestones. Services own all transactions. Controllers never open domain transactions or write SQL directly.

---

## What Prior Milestones Established

- **v0.1.0**: Project skeleton, schema, seed data, match creation, minimal live match stub
- **v0.2.0**: Production authentication, sessions, CSRF middleware, route protection
- **v0.3.0**: Full player management, match creation/edit flows
- **v0.4.0**: Complete match preparation with server-side validation — matches can now reach `prepared` state

This milestone makes the live match production-ready.

---

## Critical Invariants

**Score is always recalculated from `match_events`** — never stored as an increment counter. The `ScoreService::calculate(int $matchId)` method is the single source of truth for the score. This is checked at every score display, not once and cached.

**Only prepared matches can start** — `POST /matches/{id}/start` must validate `state === 'prepared'` and reject `planned` with a clear error.

**Finished match is terminal** — once `state = 'finished'`, no state-change route may succeed. Every service method that modifies match state must check for `finished` first.

**Period transitions follow the critical behavior spec** — read `docs/BarePitch-v2-05-critical-behavior-specifications-v1.0.md` for the exact period state machine. Do not invent intermediate states.

**Multi-entity live writes are transactional** — starting a match (state change + period creation) and finishing a match (state change + period close) must be wrapped in database transactions.

**Assist eligibility** — assist is optional and only available for own goals (not opponent goals); the assist player must be a different active field player from the scorer.

---

## Coding Philosophy

Same as all prior milestones. No invention. Every write path authenticated → authorized → validated → transacted → safe on failure.

---

## Global Exclusions for This Milestone

- Substitutions (v0.6.0)
- Cards (v0.6.0)
- Penalties during match (v0.7.0)
- Extra time (v0.7.0)
- Penalty shootout (v0.7.0)
- Livestream (v0.8.0)
- Corrections (v0.8.0)
- Ratings

---

## Required Documentation

- All docs from `-00` through `-12`
- Focus especially on `-05` (critical behavior spec), `-04` (state/derived data policy), `-09` (UI interaction specs)

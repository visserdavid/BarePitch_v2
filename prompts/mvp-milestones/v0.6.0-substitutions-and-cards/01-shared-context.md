# Shared Context — v0.6.0 Substitutions and Cards

# Purpose
Reusable reference for all prompts in the v0.6.0 bundle. Read this before executing any subsequent prompt in this directory.

---

## Milestone Identity

**Version**: v0.6.0  
**Name**: Substitutions and Cards  
**Goal**: Add live lineup changes (substitutions with playing time tracking), yellow cards, red cards, and red-card field restrictions.

---

## Technical Stack

PHP, MySQL, plain CSS, vanilla JS. No framework, no build pipeline.

---

## Architecture

Same layer rules as all prior milestones. Services own all transactions. Substitution is a multi-entity write and must be fully transactional.

---

## What Prior Milestones Established

- v0.1.0–v0.4.0: full stack, auth, player management, match preparation
- v0.5.0: live match core — start, goals, period management, finish, summary

This milestone extends live match with lineup changes and disciplinary events.

---

## Critical Invariants

**A player cannot appear twice on the field simultaneously** — any operation that would result in a player occupying two positions must be rejected server-side.

**Playing time is stored in seconds (integer)** — not as timestamps or floating-point minutes. Calculate `seconds_played = (exit_minute * 60) - entry_second_mark`. Use match clock minutes, not wall-clock time.

**Substitution is a multi-entity write** — it must update the outgoing player's lineup record, update the incoming player's lineup record, stop playing time for outgoing, start playing time for incoming, and insert a substitution event — all in one transaction. If any step fails, roll back everything.

**Sent-off flag is permanent within the match** — once `sent_off = 1` is set in `match_lineup` for a player in this match, it cannot be cleared. This flag is checked by:
1. Substitution incoming player eligibility
2. Penalty taker eligibility (v0.7.0)
3. Shootout taker eligibility (v0.7.0)

**Red card removes player from field immediately** — the player's `match_lineup` record must reflect removal from the field in the same transaction as the red card event insertion.

**Field player count** — the live screen should display the current number of active field players, which decreases by 1 for each red card issued. There is no replacement for a sent-off player.

---

## Coding Philosophy

Same as all prior milestones. No invention — use exactly the event_type values, playing time storage fields, and lineup fields from the docs.

---

## Global Exclusions for This Milestone

- Penalties during match (v0.7.0)
- Extra time (v0.7.0)
- Penalty shootout (v0.7.0)
- Livestream (v0.8.0)
- Corrections (v0.8.0)
- Ratings, training, statistics expansion

---

## Required Documentation

All docs `-00` through `-12`. Focus especially on `-05` (critical behavior spec for substitution and card rules).

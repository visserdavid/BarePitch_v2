# Score Recalculation After Correction — v0.8.0

# Purpose
Implement `ScoreRecalculationService`, which is called by `CorrectionService` whenever a correction may have changed the match score or shootout score. Score is always re-derived from source records — never updated by increment or decrement.

# Required Context
See `01-shared-context.md`. This service is a dependency of `07-correct-finished-match-end-to-end.md`. Build this before implementing correction routes.

# Required Documentation
- `docs/BarePitch-v2-04-state-and-derived-data-policy-v1.0.md` — derived data recalculation triggers
- `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md` — `match_event.event_type` values, `match.goals_scored`, `match.goals_conceded`, `penalty_shootout_attempt` schema

# Scope

## `ScoreRecalculationService`

Create `app/Services/ScoreRecalculationService.php`.

```php
<?php

namespace App\Services;

class ScoreRecalculationService
{
    public function __construct(
        private \PDO $db
    ) {}

    /**
     * Recalculate goals_scored and goals_conceded on the match row
     * by counting from match_event source records.
     *
     * This must be called inside an open transaction when used by CorrectionService.
     */
    public function recalculateMatchScore(int $matchId): void
    {
        // Verify the match exists and is finished
        $stmt = $this->db->prepare("SELECT id, status FROM `match` WHERE id = :id FOR UPDATE");
        $stmt->execute(['id' => $matchId]);
        $match = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($match === false) {
            throw new \App\Domain\Exceptions\NotFoundException('Match not found: ' . $matchId);
        }

        // Recount goals from source events
        // Exact event_type values must match docs/-01; check against the domain schema before using
        $stmt = $this->db->prepare("
            SELECT
                SUM(CASE WHEN event_type IN ('goal', 'penalty') AND team_side = 'own' AND outcome = 'scored' THEN 1 ELSE 0 END) AS own_goals,
                SUM(CASE WHEN event_type IN ('goal', 'penalty') AND team_side = 'opponent' AND outcome = 'scored' THEN 1 ELSE 0 END) AS opponent_goals
            FROM match_event
            WHERE match_id = :match_id
        ");
        $stmt->execute(['match_id' => $matchId]);
        $counts = $stmt->fetch(\PDO::FETCH_ASSOC);

        $goalsScored    = (int)($counts['own_goals'] ?? 0);
        $goalsConceded  = (int)($counts['opponent_goals'] ?? 0);

        // Update cached score columns on the match row
        $stmt = $this->db->prepare("
            UPDATE `match`
            SET goals_scored   = :scored,
                goals_conceded = :conceded
            WHERE id = :id
        ");
        $stmt->execute([
            'scored'   => $goalsScored,
            'conceded' => $goalsConceded,
            'id'       => $matchId,
        ]);
    }

    /**
     * Recalculate the shootout score from penalty_shootout_attempt source records.
     *
     * Shootout score is never added to the normal match score.
     * Must be called inside an open transaction when used by CorrectionService.
     */
    public function recalculateShootoutScore(int $matchId): void
    {
        $stmt = $this->db->prepare("
            SELECT
                SUM(CASE WHEN team_side = 'own'      AND outcome = 'scored' THEN 1 ELSE 0 END) AS own_scored,
                SUM(CASE WHEN team_side = 'opponent' AND outcome = 'scored' THEN 1 ELSE 0 END) AS opponent_scored
            FROM penalty_shootout_attempt
            WHERE match_id = :match_id
        ");
        $stmt->execute(['match_id' => $matchId]);
        $counts = $stmt->fetch(\PDO::FETCH_ASSOC);

        $ownScored      = (int)($counts['own_scored'] ?? 0);
        $opponentScored = (int)($counts['opponent_scored'] ?? 0);

        // Update shootout score cache on the match row
        // Verify exact column names in docs/-01 (may be shootout_goals_scored / shootout_goals_conceded)
        $stmt = $this->db->prepare("
            UPDATE `match`
            SET shootout_goals_scored   = :own,
                shootout_goals_conceded = :opponent
            WHERE id = :id
        ");
        $stmt->execute([
            'own'      => $ownScored,
            'opponent' => $opponentScored,
            'id'       => $matchId,
        ]);
    }
}
```

## Column Name Verification

Before implementing, grep `database/migrations/` for the exact column names on the `match` table:

```bash
grep -n "goals_scored\|goals_conceded\|shootout" database/migrations/*.sql
```

If the column names differ from the placeholders above, update the SQL to match the actual schema. Do not invent columns.

## Integration Points

`ScoreRecalculationService` is injected into `CorrectionService`. It must be called:

1. **After correcting a `match_event` row** — if the event is of type `goal` or `penalty`, or if `outcome` or `team_side` was among the changed fields
2. **After correcting a `penalty_shootout_attempt` row** — always, since any change to an attempt may change the shootout score

Both calls happen **inside the open transaction** so that the cached score and source data change atomically.

## What This Service Does NOT Do

- Does not query `match_shootout_attempts` for the normal match score
- Does not query `match_events` for the shootout score
- Does not emit events, send notifications, or trigger any side effects beyond the SQL UPDATE
- Does not open its own transaction — it operates inside the caller's transaction
- Does not change `match.status` — the finished state is not touched

# Out of Scope
- Live score recalculation during an active match (handled by `ScoreService::calculate()` in v0.5.0)
- Score display logic (handled by views and `ScoreService`)
- Shootout score display (handled by `ShootoutService::getShootoutScore()`)

# Architectural Rules
- This service contains only recalculation SQL — no business rules, no state transitions
- SQL uses prepared statements with `:named` parameters — no string interpolation
- `FOR UPDATE` on the match row prevents concurrent recalculations from racing
- Column names must come from the schema doc, not invented

# Acceptance Criteria
- `recalculateMatchScore()` sets `goals_scored` and `goals_conceded` on the match row to counts derived from `match_event` source records
- `recalculateShootoutScore()` sets shootout score columns from `penalty_shootout_attempt` source records
- Neither method touches `match.status`
- Both methods are safe to call when no events or attempts exist (result: 0)
- A corrected event (e.g., `team_side` changed from `own` to `opponent`) causes the score to reflect the corrected value after recalculation

# Verification
- PHP syntax check
- Unit test: insert 2 own goals and 1 opponent goal in match_event; call `recalculateMatchScore()`; verify `goals_scored = 2`, `goals_conceded = 1`
- Unit test: change one event's `team_side` to `opponent`; call `recalculateMatchScore()`; verify `goals_scored = 1`, `goals_conceded = 2`
- Unit test: insert 4 own scored and 3 opponent scored in shootout; call `recalculateShootoutScore()`; verify shootout columns correct
- Unit test: call both methods on a match with zero events — verify no error, zeros stored

# Handoff Note
`06-audit-logging.md` implements `AuditService::log()`. `07-correct-finished-match-end-to-end.md` calls both `ScoreRecalculationService` and `AuditService` inside correction transactions.

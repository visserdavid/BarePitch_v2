# Track Playing Time End to End

# Purpose
Implement `PlayingTimeService` for consistent playing time recording across all player movement scenarios: starters (start on match start), incoming substitutes (start on substitution), outgoing substitutes (stop on substitution), red-carded players (stop on red card).

# Required Context
See `01-shared-context.md`. Match start from v0.5.0 establishes the initial field players. Substitution from prompt 02 calls this service.

# Required Documentation
- `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md` — playing time storage schema (check for `player_match_time` table or a `seconds_played` column in `match_lineup`)

# Scope

## Playing time storage

Check `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md` for the exact storage structure. Possibilities:
- A `player_match_time` table: `(match_id, player_id, entry_minute, exit_minute, seconds_played)`
- A `seconds_played` column in `match_lineup`
- Another structure defined in the docs

Use exactly what the docs define. Do not invent a new table.

## `PlayingTimeService` (`app/Services/PlayingTimeService.php`)

```php
class PlayingTimeService {

    /**
     * Record that a player entered the field at a given match minute.
     * Called when: match starts (for all starters) and when a substitution brings on a new player.
     */
    public function startPlayerTime(int $matchId, int $playerId, int $minuteEntered): void {
        // Store entry minute for this player in this match
        // If a record exists (e.g., player re-enters — not possible normally but guard against it),
        // only create a new entry if no active entry exists
        $this->repository->upsertEntry($matchId, $playerId, $minuteEntered);
    }

    /**
     * Record that a player left the field at a given match minute.
     * Calculate seconds_played = (minuteExited - minuteEntered) * 60.
     * Called when: substituted off, red card issued.
     */
    public function stopPlayerTime(int $matchId, int $playerId, int $minuteExited): void {
        $entry = $this->repository->getActiveEntry($matchId, $playerId);
        if (!$entry) {
            // Player has no active entry — log warning but don't throw
            error_log("PlayingTime: no active entry for player {$playerId} in match {$matchId}");
            return;
        }
        $secondsPlayed = max(0, ($minuteExited - $entry['entry_minute']) * 60);
        $this->repository->recordExit($matchId, $playerId, $minuteExited, $secondsPlayed);
    }
}
```

## Integration with match start (update v0.5.0 `LiveMatchService::startMatch()`)

After creating the first period, start playing time for all starters:
```php
$starters = $this->lineupRepository->getStartersForMatch($matchId);
foreach ($starters as $starter) {
    $this->playingTimeService->startPlayerTime($matchId, $starter['player_id'], 0); // minute 0 = kickoff
}
```

## Integration with substitution (already included in prompt 02)

`SubstitutionService::perform()` calls:
- `$this->playingTimeService->stopPlayerTime($matchId, $outgoingId, $minute)`
- `$this->playingTimeService->startPlayerTime($matchId, $incomingId, $minute)`

## Integration with red card (prompt 04 calls)

`CardService::registerRedCard()` (implemented in next prompt) will call:
- `$this->playingTimeService->stopPlayerTime($matchId, $playerId, $minute)`

## Playing time at match end

When `LiveMatchService::finishMatch()` is called, players still on the field have their time implicitly ended at the final minute. Optionally, add a `finalizePlayingTime(int $matchId, int $finalMinute)` call in `finishMatch()` that calls `stopPlayerTime` for all players with an active entry.

# Out of Scope
- Statistics display of playing time (v0.9.0)
- Playing time for extra-time periods (v0.7.0 should integrate with this service)

# Architectural Rules
- Playing time is stored as integer seconds
- `PlayingTimeService` is called from `SubstitutionService`, `CardService`, `LiveMatchService` — never directly from controllers
- Entry minute and exit minute are match clock minutes (integers)

# Acceptance Criteria
- Starter's playing time entry created when match starts (minute 0)
- Incoming substitute's entry created at substitution minute
- Outgoing substitute's exit recorded at substitution minute; `seconds_played` calculated correctly
- Red-carded player's exit recorded when red card issued (implemented in prompt 04)
- `seconds_played` is stored as a positive integer

# Verification
- PHP syntax check
- Start a match; verify playing time entries created for all starters
- Perform a substitution at minute 45; verify outgoing has `exit_minute=45`, incoming has `entry_minute=45`
- Verify `seconds_played` for outgoing = 45 * 60 = 2700

# Handoff Note
`04-issue-card-end-to-end.md` implements yellow and red card events and calls `PlayingTimeService::stopPlayerTime()` for red cards.

# Finish Match and Summary End to End

# Purpose
Implement the match finish action and the finished match summary view. The `finished` state is terminal — no state change is possible after finishing.

# Required Context
See `01-shared-context.md`. Period management from `04-manage-periods-end-to-end.md`.

# Required Documentation
- `docs/BarePitch-v2-05-critical-behavior-specifications-v1.0.md` — finish rules, terminal state
- `docs/BarePitch-v2-04-state-and-derived-data-policy-v1.0.md` — state policy

# Scope

## Routes
```php
$router->post('/matches/{id}/finish',  [\App\Http\Controllers\LiveMatchController::class, 'finish']);
$router->get('/matches/{id}/summary',  [\App\Http\Controllers\MatchController::class, 'summary']);
```

## `POST /matches/{id}/finish`

**Confirmation requirement**: POST body must include `confirm=1`. The UI shows a confirmation modal or two-step button before submitting. Server validates the confirmation field.

`LiveMatchService::finishMatch(int $matchId, bool $confirmed): void`

```php
public function finishMatch(int $matchId, bool $confirmed): void {
    if (!$confirmed) {
        throw new \InvalidArgumentException('Finish confirmation required.');
    }

    $match = $this->matchRepository->findById($matchId);
    if ($match['state'] === 'finished') {
        throw new InvalidStateException('Match is already finished.');
    }
    if ($match['state'] !== 'active') {
        throw new InvalidStateException('Only active matches can be finished.');
    }

    $db = Database::connection();
    $db->beginTransaction();
    try {
        // Close any unclosed period
        $db->prepare("UPDATE match_periods SET ended_at = NOW() WHERE match_id = ? AND ended_at IS NULL")
           ->execute([$matchId]);

        // Finish the match
        $db->prepare("UPDATE matches SET state = 'finished', finished_at = NOW() WHERE id = ?")
           ->execute([$matchId]);

        $db->commit();
    } catch (\Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}
```

After finishing: redirect to `GET /matches/{id}/summary`.

## Terminal state enforcement

In all `LiveMatchService` methods that modify match state, add at the top:
```php
$match = $this->matchRepository->findById($matchId);
if ($match['state'] === 'finished') {
    throw new InvalidStateException('This match is finished and cannot be modified.');
}
```

This must be present in: `startMatch`, `endFirstHalf`, `startSecondHalf`, `endSecondHalf`, `register` (goal events), and any other event registration method.

## `GET /matches/{id}/summary`

`MatchController::summary(int $id): void`

Load and render:
1. Match metadata: opponent, home_away, match_type, scheduled_at, phase name
2. Final score: `ScoreService::calculate($id)` — same method used on the live screen
3. Full event timeline: all `match_events` ordered by `minute ASC`, `id ASC`; for each event: minute, type, player name (join to players table), assist player name if set
4. Lineup at end: all `match_lineup` rows with player names, positions, is_starter flag

Authorization: any authenticated user with team access can view.

## Summary view (`app/Views/matches/summary.php`)

Mobile-first layout:
- Match header: "Demo FC X - Y [Opponent]" with match metadata
- Event timeline: each event as a row with minute badge, event icon, player name
- Lineup section: starters (with positions) and bench

Include a "Back to matches" link.

## Add finish button to live screen (update `02-open-live-match-and-start-end-to-end.md`'s view)

After both halves are ended (determined by checking current period state), show the "Finish Match" button:

```html
<form action="/matches/<?= e($match['id']) ?>/finish" method="POST">
    <input type="hidden" name="_csrf" value="<?= e(\App\Http\Helpers\CsrfHelper::getToken()) ?>">
    <input type="hidden" name="confirm" value="1">
    <button type="submit" class="btn-danger" onclick="return confirm('Finish this match?')">
        Finish Match
    </button>
</form>
```

# Out of Scope
- Corrections to finished match data (v0.8.0)
- Livestream (v0.8.0)
- Ratings

# Architectural Rules
- Finish is transactional (close unclosed periods + set state=finished)
- Terminal state check in all event-writing service methods
- Score derived from events in summary — never from a stored column
- Confirmation required both client-side and server-side

# Acceptance Criteria
- `POST /matches/{id}/finish` (with confirm=1) transitions `active` → `finished`
- `POST /matches/{id}/finish` without confirm=1 is rejected
- Summary shows correct final score (recalculated from events)
- Score unchanged after refresh
- `POST /matches/{id}/start` on finished match: safe error
- `POST /matches/{id}/events/goal` on finished match: safe error
- `POST /matches/{id}/finish` on already-finished match: safe error

# Verification
- PHP syntax check
- Complete flow: start → goals → periods → finish → view summary → verify score
- Attempt to POST start/goal/finish on finished match → verify all return safe errors
- Refresh summary → verify score consistent

# Handoff Note
`06-authorization-and-csrf.md` performs a cross-cutting security audit for all v0.5.0 routes.

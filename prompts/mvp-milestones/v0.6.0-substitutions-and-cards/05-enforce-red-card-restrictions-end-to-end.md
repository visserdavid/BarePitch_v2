# Enforce Red Card Restrictions End to End

# Purpose
Enforce all downstream restrictions from the `sent_off` flag: sent-off players cannot be substituted on, cannot take penalties (flag available for v0.7.0), and the live screen correctly reflects the reduced field count.

# Required Context
See `01-shared-context.md`. `sent_off = 1` set by `04-issue-card-end-to-end.md`. Substitution eligibility check in `02-substitute-player-end-to-end.md` already excludes sent-off players — this prompt verifies and extends those checks.

# Required Documentation
- `docs/BarePitch-v2-05-critical-behavior-specifications-v1.0.md` — red card restrictions
- `docs/BarePitch-v2-02-authorization-matrix-v1.0.md` — any penalty-related restrictions

# Scope

## Verify substitution incoming player check

In `SubstitutionService::perform()` (prompt 02), the `sent_off` check is:
```php
if ($incoming['sent_off']) {
    throw new \InvalidArgumentException('Sent-off player cannot be substituted on.');
}
```

Verify this check reads from `match_lineup.sent_off` for the correct `match_id` — not from a generic player status. A player sent off in a different match must not be blocked.

## Eligible penalty takers query

Create `LineupRepository::getEligiblePenaltyTakers(int $matchId): array`:
```php
public function getEligiblePenaltyTakers(int $matchId): array {
    $stmt = $this->db->prepare("
        SELECT ml.*, p.name, psc.jersey_number, psc.position
        FROM match_lineup ml
        JOIN players p ON p.id = ml.player_id
        LEFT JOIN player_season_context psc ON psc.player_id = ml.player_id
        WHERE ml.match_id = ?
          AND ml.sent_off = 0
          AND p.active = 1
        ORDER BY psc.jersey_number
    ");
    $stmt->execute([$matchId]);
    return $stmt->fetchAll();
}
```

This query is used by v0.7.0 for in-match penalty taker selection and shootout taker selection. It must exclude:
- Sent-off players (`sent_off = 0`)
- Deactivated players (`players.active = 1`)

The eligible list includes both field players AND bench players who are not sent off (all squad members present for this match who have not been sent off).

## Field player count display

Update the live match screen to show the current field player count:
```php
$fieldPlayerCount = $lineupRepository->countActiveFieldPlayers($matchId);
```

```php
public function countActiveFieldPlayers(int $matchId): int {
    $stmt = $this->db->prepare("
        SELECT COUNT(*) FROM match_lineup
        WHERE match_id = ? AND is_starter = 1 AND sent_off = 0
    ");
    $stmt->execute([$matchId]);
    return (int)$stmt->fetchColumn();
}
```

Show in the score header or period display:
```html
<span class="field-count"><?= e($fieldPlayerCount) ?> players on field</span>
```

## Bench availability display

In the substitution modal, the incoming player list must exclude sent-off players. This is already enforced server-side in `SubstitutionService`, but the UI should also filter them out for a better mobile experience:
```php
$eligibleBenchPlayers = $lineupRepository->getEligibleBenchPlayers($matchId);
// WHERE is_starter = 0 AND sent_off = 0
```

# Out of Scope
- Full penalty taker UI (v0.7.0)
- Shootout taker selection (v0.7.0)

# Architectural Rules
- `sent_off` restriction is match-scoped — checked against `match_lineup.sent_off` for the specific `match_id`
- `getEligiblePenaltyTakers()` must be in `LineupRepository` so v0.7.0 can reuse it without duplicating the query

# Acceptance Criteria
- Red-carded player does not appear in the substitution incoming player list (UI)
- Red-carded player as incoming player in `POST /matches/{id}/events/substitution`: server-side 400/rejection
- `getEligiblePenaltyTakers()` returns active, non-sent-off squad members
- Field count on live screen reflects number of current field players (decreases by 1 after red card)

# Verification
- PHP syntax check
- Register red card for a field player; open substitution modal → verify they are not in the incoming list
- Attempt to substitute them on via direct POST → verify server-side rejection
- Query `getEligiblePenaltyTakers()` after red card → verify they are not in the result
- Verify field player count decrements on live screen after red card

# Handoff Note
`06-testing-and-verification.md` adds automated tests for all v0.6.0 scenarios.

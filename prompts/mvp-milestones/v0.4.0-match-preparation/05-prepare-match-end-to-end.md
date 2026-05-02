# Prepare Match End to End

# Purpose
Implement the prepare match action: a single atomic operation that re-validates all preparation rules server-side and transitions the match from `planned` to `prepared`. If any rule fails, the match remains `planned` and no partial state is written.

# Required Context
See `01-shared-context.md`. Attendance (prompt 02), guest players (prompt 03), and lineup (prompt 04) must be fully implemented. The prepare action is the culmination of those.

# Required Documentation
- `docs/BarePitch-v2-05-critical-behavior-specifications-v1.0.md` — preparation rules and state machine
- `docs/BarePitch-v2-04-state-and-derived-data-policy-v1.0.md` — state transition policy
- `docs/BarePitch-v2-10-data-integrity-test-scenarios-v1.0.md` — MP-01 through MP-06, LU-01 through LU-04

# Scope

## `POST /matches/{id}/prepare`

**Authorization**: coach or administrator. CSRF required. Trainer and team_manager return 403.

**Confirmation**: the prepare action is a critical state change. The UI must require a deliberate action:
- Option A: a two-step confirmation modal (JavaScript) that shows a summary before submitting
- Option B: a POST body field `confirm=1` that the form only includes after a confirmation step
- Either way, the server does not require the `confirm` field — the deliberate action is a UI concern. The server simply executes the prepare validation.

**`MatchPreparationService::prepare(int $matchId, int $userId): void`**

This method owns the entire prepare transaction. No partial state must be written if any check fails.

```php
public function prepare(int $matchId, int $userId): void {
    $this->db->beginTransaction();
    try {
        $match = $this->matchRepository->findById($matchId);

        // Rule 1: match must be in planned state
        if ($match['state'] !== 'planned') {
            throw new InvalidStateException('Match is not in planned state.');
        }

        $attendance = $this->attendanceRepository->getForMatch($matchId);
        $presentPlayers = array_filter($attendance, fn($a) => $a['status'] === 'present');
        $injuredPlayerIds = array_column(
            array_filter($attendance, fn($a) => $a['status'] === 'injured'),
            'player_id'
        );

        // Rule 2: at least 11 players present
        if (count($presentPlayers) < 11) {
            throw new PreparationValidationException('At least 11 players must be marked present.');
        }

        // Rule 3: total present ≤ maximum allowed (per docs)
        $maxPlayers = /* read from config or docs constant */;
        if (count($presentPlayers) > $maxPlayers) {
            throw new PreparationValidationException("Too many players present (max {$maxPlayers}).");
        }

        // Rule 4: formation must be selected
        if (empty($match['formation_id'])) {
            throw new PreparationValidationException('A formation must be selected.');
        }

        $formationPositions = $this->formationRepository->getPositions($match['formation_id']);
        $lineup = $this->lineupRepository->getStartersForMatch($matchId);
        $starterPlayerIds = array_column($lineup, 'player_id');
        $starterPositionIds = array_column($lineup, 'position_id');

        // Rule 5: all starting positions for the formation must be filled
        $requiredPositionIds = array_column($formationPositions, 'id');
        $missingPositions = array_diff($requiredPositionIds, $starterPositionIds);
        if (!empty($missingPositions)) {
            throw new PreparationValidationException('Not all starting positions are filled.');
        }

        // Rule 6: every starter must be present
        $presentPlayerIds = array_column($presentPlayers, 'player_id');
        $absentStarters = array_diff($starterPlayerIds, $presentPlayerIds);
        if (!empty($absentStarters)) {
            throw new PreparationValidationException('One or more starters are not marked present.');
        }

        // Rule 7: no injured starter
        $injuredStarters = array_intersect($starterPlayerIds, $injuredPlayerIds);
        if (!empty($injuredStarters)) {
            throw new PreparationValidationException('One or more starters are injured.');
        }

        // Rule 8: no duplicate player in multiple starting positions
        if (count(array_unique($starterPlayerIds)) !== count($starterPlayerIds)) {
            throw new PreparationValidationException('A player appears in multiple starting positions.');
        }

        // Rule 9: no duplicate field slot
        if (count(array_unique($starterPositionIds)) !== count($starterPositionIds)) {
            throw new PreparationValidationException('A position slot is occupied by multiple players.');
        }

        // All checks passed — transition state
        $this->matchRepository->setState($matchId, 'prepared');
        $this->db->commit();

    } catch (\Throwable $e) {
        $this->db->rollBack();
        throw $e;
    }
}
```

## `MatchPreparationController::prepare()`
```php
public function prepare(int $matchId): void {
    if (!MatchPreparationPolicy::canPrepare()) {
        http_response_code(403);
        render('errors/403.php');
        return;
    }

    try {
        $this->preparationService->prepare($matchId, CurrentUser::getId());
        redirect("/matches/{$matchId}?prepared=1");
    } catch (PreparationValidationException $e) {
        // Return to preparation screen with error message
        redirect("/matches/{$matchId}/prepare?error=" . urlencode($e->getMessage()));
    } catch (InvalidStateException $e) {
        redirect("/matches/{$matchId}?error=" . urlencode($e->getMessage()));
    }
}
```

## Exception classes
Create `app/Domain/Exceptions/PreparationValidationException.php` and `InvalidStateException.php` if not already present.

## Match state check on all subsequent routes
After preparation is complete, any route that was valid for `planned` state only must check that state has not changed:
- `POST /matches/{id}/attendance` — only valid if `planned`
- `POST /matches/{id}/lineup` — only valid if `planned`
- `POST /matches/{id}/prepare` — only valid if `planned`

# Out of Scope
- Match start (v0.5.0)
- Substitution validation during live match (v0.6.0)

# Architectural Rules
- The entire prepare validation is in `MatchPreparationService::prepare()` — not split across controller and service
- The transaction wraps the state read, all validations (which use DB queries), and the state write
- Controller catches domain exceptions and redirects with errors — never swallows exceptions silently

# Acceptance Criteria
- Valid lineup: match transitions from `planned` to `prepared`
- Fewer than 11 present: blocked, descriptive error shown, state remains `planned`
- More than max players present: blocked, error shown
- No formation selected: blocked, error shown
- Incomplete lineup (unfilled position): blocked, error shown
- Absent starter: blocked, error shown
- Injured starter: blocked, error shown
- Duplicate player in two positions: blocked, error shown
- Duplicate position slot: blocked, error shown
- State remains `planned` after any failed prepare attempt (verified by DB query)

# Verification
- PHP syntax check all new/modified files
- Test each blocking condition in isolation: set up the failing condition, call prepare, verify error message and state = planned in DB
- Test the success path: valid setup → prepare → verify state = prepared in DB
- Attempt to `POST /matches/{id}/prepare` as trainer — verify 403

# Handoff Note
`06-testing-and-verification.md` adds the automated tests covering MP-01 through MP-06, LU-01 through LU-04, and GP-01 through GP-03 from the test scenarios document.

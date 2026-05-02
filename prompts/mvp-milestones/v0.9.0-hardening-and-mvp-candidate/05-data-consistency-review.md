# Data Consistency Review — v0.9.0

# Purpose
Systematically verify every item in the data consistency checklist from `01-shared-context.md`. Confirm that derived data is always recalculated from its source, that state transitions never leave the data model in a partially-updated state, and that audit logging is never skipped. Fix every gap found.

---

# Required Context
See `01-shared-context.md`. Prompts 02 through 04 must be complete. This prompt focuses on data correctness, not security — but fixes may overlap with files touched in prompt 04.

---

# Required Documentation
- `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md` — canonical schema; source-of-truth columns vs. derived columns
- `docs/BarePitch-v2-04-state-and-derived-data-policy-v1.0.md` — which data is derived; when recalculation must run
- `docs/BarePitch-v2-05-critical-behavior-specifications-v1.0.md` — correction rules, locking, audit requirements
- `docs/BarePitch-v2-10-data-integrity-test-scenarios-v1.0.md` — DI test scenarios to verify

---

# Scope

## Audit Method

Work through each checklist item in order. For each item:
1. Identify all code paths relevant to the item
2. Trace the data flow from the user action to the final stored state
3. Verify the item passes or fails
4. If it fails: fix it immediately
5. Record the result in `DATA-CONSISTENCY-AUDIT.md` in the project root

---

## D-01 — Score Is Source-Derived from Events

**Invariant**: the match score is never manually set. It is always computed from goal events in `match_events`.

Steps:
1. Find the recalculation method (likely in `MatchScoreService` or `MatchEventService`)
2. Verify it counts rows in `match_events` with `event_type = 'goal'` for the team and opponent
3. Verify it is called after every goal event write (add, edit, delete)
4. Verify it is called after every finished-match correction that touches goal events
5. Verify no controller or service sets `matches.home_score` or `matches.away_score` directly without going through the recalculation method

**Search pattern**:
```bash
grep -rn "home_score\|away_score\|score" app/Services/ app/Repositories/ --include="*.php"
```

Review every direct write to a score column. Any direct write that bypasses recalculation is a gap.

**Fix pattern** (if score is set directly in a correction without recalculation):
```php
// WRONG:
$this->matchRepository->updateScore($matchId, $homeScore, $awayScore);

// CORRECT:
// 1. Write the corrected event
$this->matchEventRepository->update($eventId, $correctedData);
// 2. Recalculate score from all events
$this->matchScoreService->recalculate($matchId);
// Both in the same transaction
```

---

## D-02 — Shootout Score Is Separate from Normal Score

**Invariant**: penalty shootout goals are stored separately and are NEVER added to the normal match score.

Steps:
1. Find where shootout scores are stored (check `docs/BarePitch-v2-01-domain-model-and-schema-v1.0.md` for the column/table)
2. Verify the shootout score recalculation method reads only from the shootout event type (not from regular goal events)
3. Verify the normal score recalculation method excludes shootout events
4. Verify the match summary view displays shootout score in a separate field, not merged with the normal score
5. Verify the statistics query from prompt 02 excludes shootout goals from `goals_for` and `goals_against`

**Search pattern**:
```bash
grep -rn "shootout\|penalty_shootout\|shootout_score" app/ --include="*.php"
```

Review every location. Confirm there is no path where shootout goals contribute to normal score totals.

---

## D-03 — Lineup Current State Valid After Substitutions and Red Cards

**Invariant**: after any substitution or red card, the `match_lineup` table reflects the current on-field state. A substituted-off player is no longer in the lineup. A substitute is in the lineup at the correct position (or bench, depending on the formation). A red-carded player is removed from the lineup and cannot return.

Steps:
1. Find `SubstitutionService` (or equivalent)
2. Verify it removes the outgoing player from the active lineup and inserts the incoming player, in the same transaction
3. Verify it sets the outgoing player's `exit_minute` (for playing time calculation)
4. Find `CardService` (or equivalent)
5. Verify that recording a red card marks the player as `red_carded` in `match_lineup` and removes them from the active field positions
6. Verify that a red-carded player cannot be selected as a substitute target or event participant in subsequent actions

**Transaction check**: both the substitution and the card event must be written in a single transaction. If the event write succeeds but the lineup update fails, the data would be inconsistent.

**Fix pattern** (if substitution does not update lineup atomically):
```php
$this->db->beginTransaction();
try {
    $this->matchEventRepository->insertSubstitution($matchId, $outId, $inId, $minute);
    $this->lineupRepository->substitutePlayer($matchId, $outId, $inId, $minute);
    $this->db->commit();
} catch (\Throwable $e) {
    $this->db->rollBack();
    throw $e;
}
```

---

## D-04 — Playing Time Stored in Seconds and Consistent

**Invariant**: every player's `playing_time_seconds` is derived from their entry and exit times in `match_lineup`. It is recalculated whenever a substitution is recorded or the match is finished.

Steps:
1. Find where `playing_time_seconds` is computed
2. Verify starters: `playing_time_seconds = (exit_minute ?? match_duration_minutes) * 60`
3. Verify substitutes: `playing_time_seconds = (exit_minute ?? match_duration_minutes - entry_minute) * 60`
4. Verify the computation handles extra time correctly (match duration may exceed 90 minutes)
5. Verify `playing_time_seconds` is recalculated when:
   - A substitution is recorded
   - The match is finished (to finalize all remaining players' times)
   - A finished-match correction changes a substitution event

**Consistency check**: sum all players' `playing_time_seconds` for a match and verify it is approximately `11 * match_duration_seconds` (accounting for red cards and substitutions that reduce the count at any given moment).

This is not a hard invariant (a team can play with fewer than 11 due to red cards), but if the sum is wildly off, it signals a calculation bug.

---

## D-05 — Corrections Recalculate Derived Data

**Invariant**: every finished-match correction triggers a full recalculation of all derived data affected by the corrected event.

Steps:
1. Find `MatchCorrectionService` (or equivalent)
2. Identify every type of correction the service handles (goal add/edit/delete, assist add/edit/delete, card add/edit/delete, substitution edit)
3. For each correction type, verify:
   - The correction write and the recalculation are in the same transaction
   - The recalculation covers score (for goal corrections), lineup state (for card/substitution corrections), and playing time (for substitution corrections)
4. Verify there is no early-return path in the correction service that skips recalculation (e.g., if the corrected value is the same as the existing value, recalculation still runs — or the correction is a no-op and nothing is written)

**Fix pattern** (if a correction type is missing recalculation):
```php
public function correctGoalEvent(int $matchId, int $eventId, array $data): void {
    $this->db->beginTransaction();
    try {
        $this->auditLogRepository->write($matchId, $eventId, 'goal_corrected', $data);  // always first
        $this->matchEventRepository->update($eventId, $data);
        $this->matchScoreService->recalculate($matchId);   // always after correction
        $this->db->commit();
    } catch (\Throwable $e) {
        $this->db->rollBack();
        throw $e;
    }
}
```

---

## D-06 — Locking Prevents Silent Overwrite

**Invariant**: before writing a finished-match correction, the service checks an optimistic lock version against the version the client submitted. If the versions differ, the correction is rejected with a safe error (never silently overwritten).

Steps:
1. Find the optimistic locking implementation (likely a `lock_version` column on `matches` or the correction subject)
2. Verify the lock check is inside the transaction and uses a `SELECT ... FOR UPDATE` or equivalent pattern
3. Verify the check compares `$submittedVersion` against the DB version before writing
4. Verify a version mismatch returns a user-friendly error and does NOT write partial state
5. Verify the lock version is incremented on every successful correction write

**Fix pattern** (if lock check is outside the transaction):
```php
$this->db->beginTransaction();
try {
    // Lock the row first
    $current = $this->matchRepository->findByIdForUpdate($matchId);
    if ($current['lock_version'] !== $submittedLockVersion) {
        $this->db->rollBack();
        throw new OptimisticLockException('The match was modified by another user. Reload and try again.');
    }
    // Proceed with correction...
    $this->matchRepository->incrementLockVersion($matchId);
    $this->db->commit();
} catch (OptimisticLockException $e) {
    throw $e;
} catch (\Throwable $e) {
    $this->db->rollBack();
    throw $e;
}
```

---

## D-07 — Audit Log Never Skipped

**Invariant**: every finished-match correction writes an audit log entry in the same transaction as the correction itself. If the audit write fails, the entire transaction rolls back and the correction is not applied.

Steps:
1. Find every call site in `MatchCorrectionService` (or equivalent) where an audit entry should be written
2. Verify the audit write is the FIRST operation inside the transaction (before the correction write)
3. Verify there is no code path that writes the correction but catches/swallows the audit write exception
4. Verify the audit log table stores: `match_id`, `user_id`, `action`, `before_state`, `after_state`, `created_at` (or equivalent fields per schema)

**Swallowed exception pattern to look for (fix this if found)**:
```php
// WRONG — audit failure does not prevent correction:
try {
    $this->auditRepository->write(/* ... */);
} catch (\Throwable $e) {
    // silently ignore audit failure
}
$this->matchEventRepository->update(/* ... */);

// CORRECT — audit and correction in same transaction; either both commit or both roll back:
$this->db->beginTransaction();
$this->auditRepository->write(/* ... */);    // throws on failure — triggers rollback
$this->matchEventRepository->update(/* ... */);
$this->db->commit();
```

---

## D-08 — Output Escaping in All Views (Data Values)

This item overlaps with the security review but belongs here because it is also a data integrity concern: unescaped output can corrupt the display of legitimate data containing `<`, `>`, or `&` characters.

Steps:
1. Grep for `<?= $` and `echo $` in all view files
2. Verify every output of a variable is wrapped in `htmlspecialchars()`:
   ```bash
   grep -rn "<?= \$\|echo \$" app/Views/ --include="*.php"
   ```
3. Any result not containing `htmlspecialchars` is a gap
4. Fix using the helper pattern:
   ```php
   // Use a view helper function if available:
   function e(string $value): string {
       return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
   }
   // Then in views:
   <?= e($player['name']) ?>
   ```

---

## Audit Log File

Create `DATA-CONSISTENCY-AUDIT.md` in the project root:

```markdown
# Data Consistency Audit — v0.9.0

Date: [date]

| ID   | Item                                          | Result | Notes / Fix Applied |
|------|-----------------------------------------------|--------|---------------------|
| D-01 | Score source-derived from events              | PASS   |                     |
| D-02 | Shootout score separate from normal score     | PASS   |                     |
| D-03 | Lineup state valid after substitution/red card| PASS   |                     |
| D-04 | Playing time in seconds, consistent           | PASS   |                     |
| D-05 | Corrections recalculate derived data          | PASS   |                     |
| D-06 | Locking prevents silent overwrite             | PASS   |                     |
| D-07 | Audit log never skipped                       | PASS   |                     |
| D-08 | Output escaping on all data values            | PASS   |                     |
```

---

# Out of Scope

- Retroactive recalculation of historical data for matches completed before this milestone
- Adding new derived columns not already in the schema
- Performance optimization of recalculation queries
- Soft-delete or archival logic changes

---

# Architectural Rules

- All recalculation methods live in Services, not Repositories or Controllers
- Recalculation is always triggered by the Service that owns the state transition — never triggered by the Repository
- The audit log write is always the first statement inside a correction transaction
- Optimistic lock check is always inside the transaction, with `SELECT ... FOR UPDATE` or equivalent
- No derived data is cached in a way that can become stale without being invalidated

---

# Acceptance Criteria

All 8 items (D-01 through D-08) pass as verified in `DATA-CONSISTENCY-AUDIT.md`.

In addition:
- A test that adds a goal event and immediately reads `matches.home_score` shows the correct recalculated score
- A test that records a substitution and reads `match_lineup` shows the outgoing player removed and incoming player added
- A test that records a red card and reads `match_lineup` shows the carded player in a non-active state
- A test that submits a correction with a stale lock version receives an `OptimisticLockException` error response and the DB is unchanged
- A test that simulates an audit write failure rolls back the correction and leaves the DB unchanged

---

# Verification

1. PHP syntax check:
   ```bash
   find app/ -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"
   ```
2. Grep for direct score writes:
   ```bash
   grep -rn "home_score\|away_score" app/ --include="*.php"
   ```
   Review every result and confirm no direct write bypasses recalculation.
3. Grep for unescaped view output:
   ```bash
   grep -rn "<?= \$\|echo \$" app/Views/ --include="*.php" | grep -v "htmlspecialchars\|e("
   ```
   Expected: zero results.
4. Trace a complete correction flow manually: load a finished match, submit a goal correction, verify the score updates in the DB, verify the audit log row was written, verify the lock version incremented.
5. Confirm `DATA-CONSISTENCY-AUDIT.md` is complete with no `FAIL` rows.

---

# Handoff Note

After this prompt, the data model is internally consistent for all MVP flows. `06-authorization-and-csrf-review.md` performs the final route-by-route authorization audit. Fixes discovered in this prompt that require changing a Service may affect the authorization review — ensure both audits use the same version of the codebase.

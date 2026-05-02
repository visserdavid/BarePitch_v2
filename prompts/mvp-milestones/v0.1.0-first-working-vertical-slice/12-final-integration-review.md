# Final Integration Review — v0.1.0

# Purpose
Perform the end-to-end integration verification for the complete v0.1.0 vertical slice. Confirm the slice works from initial setup through a finished match summary, with no silent gaps or hidden assumptions.

# Required Context
See `01-shared-context.md`. All prior prompts (02–11) must be complete and all tests must pass before this review.

# Required Documentation
- `docs/BarePitch-v2-12-ai-implementation-rules-v1.0.md` — completeness requirements
- `docs/BarePitch-v2-06-mvp-scope-v1.0.md` — v0.1.0 acceptance criteria

# Scope

## Pre-review checklist

Before starting the walk-through, confirm:
- [ ] `vendor/bin/phpunit --testdox` — all tests pass
- [ ] PHP syntax check: `find . -name "*.php" -not -path "./vendor/*" -exec php -l {} \;` — zero errors
- [ ] `php scripts/migrate.php` — runs without errors on a fresh database
- [ ] `php scripts/seed.php` — runs without errors

## End-to-end walk-through

Execute each step and verify the expected outcome:

1. **Root URL** — `GET /` → HTML page renders with "BarePitch"
2. **Player list** — `GET /players` → 18 players listed for "Demo FC First Team"
3. **Match list** — `GET /matches` → empty list (no matches yet)
4. **Create match** — `GET /matches/create` → form renders with phase selector
5. **Submit match** — `POST /matches` with valid data → redirects to match detail, state=`planned`
6. **Preparation screen** — `GET /matches/{id}/prepare` → shows all 18 players with attendance controls
7. **Mark attendance** — POST attendance: 15 present, 2 absent, 1 injured → persists correctly
8. **Select formation** — POST formation → `matches.formation_id` set
9. **Fill lineup** — POST lineup with 11 starters → bench populated with 4 non-starters
10. **Attempt prepare (fail)** — mark only 9 players present; POST prepare → error shown, state remains `planned`
11. **Fix attendance** — mark 11+ present again; POST prepare → state transitions to `prepared`
12. **Attempt start planned** — create another match in `planned` state; attempt start → safe error
13. **Start match** — POST start prepared match → state=`active`; redirects to live screen
14. **Live screen** — `GET /matches/{id}/live` → score shows 0-0; lineup visible; timeline empty
15. **Register own goal** — POST goal (type=own, scorer, minute=12) → score shows 1-0; event in timeline
16. **Register opponent goal** — POST goal (type=opponent, minute=27) → score shows 1-1; event in timeline
17. **Refresh** — `GET /matches/{id}/live` → score still 1-1 (recalculated from events, not from a counter)
18. **Period transitions** — end first half → start second half → register another own goal → score 2-1
19. **End second half** — POST end second half → period closed
20. **Finish match** — POST finish with confirm=1 → state=`finished`; redirects to summary
21. **Summary** — `GET /matches/{id}/summary` → shows score 2-1; full event timeline
22. **Refresh summary** — reload page → score still 2-1 (recalculated)
23. **Attempt restart** — POST start on finished match → safe error returned
24. **CSRF test** — remove `_csrf` from a form POST (browser dev tools) → 403 returned

## Score integrity verification

After the walk-through, query the database directly:
```sql
SELECT event_type, COUNT(*) as count FROM match_events WHERE match_id = ? GROUP BY event_type;
```
Verify the count of `goal_own` and `goal_opponent` events matches the displayed score.

## Known gap documentation

If any behavior gap is discovered (something the docs specify that is not yet implemented), document it here:

```
## Known Gaps for Follow-up

- [ ] Full magic-link authentication (v0.2.0)
- [ ] Guest player selection in preparation (v0.4.0)
- [ ] Real CSRF rate limiting and session timeouts (v0.2.0)
- [any additional gaps found during this review]
```

Do NOT silently implement scope items from v0.2.0+ during this review. Document them only.

## Documentation alignment

If this review reveals that any documented behavior conflicts with the implementation (or vice versa):
1. Do NOT change behavior to paper over the conflict
2. Create a note in this file: `DOC CONFLICT: [docs file] says X but implementation does Y`
3. The docs must be updated (or the implementation fixed) before proceeding to v0.2.0

# Out of Scope
- New feature implementation except small fixes required to satisfy v0.1.0 acceptance criteria.
- Later milestone behavior such as production authentication, substitutions, cards, livestream, corrections, or audit logging.
- Rewriting source documentation unless the implementation intentionally changes documented behavior.

# Architectural Rules
- The review must verify end-to-end behavior through the application, database state, and tests.
- Fixes made during review must preserve authorization, validation, transaction, and derived-data rules from `01-shared-context.md`.
- Known gaps must be documented instead of silently accepted.

# Acceptance Criteria
- All 24 walk-through steps complete with expected outcomes
- Score integrity verified via direct DB query
- PHP syntax check: zero errors
- All automated tests pass
- Known gaps documented (not hidden)
- No doc conflicts left unresolved
- App is not labeled as `v1.0.0` — this is `v0.1.0`

# Verification
Complete the walk-through checklist. Document the test run output. State the final score from the summary page and confirm it matches the DB event count.

# Handoff Note
v0.2.0 replaces the temporary developer login with the documented magic-link authentication system. The team context, session management, and route protection will become production-ready in that milestone.

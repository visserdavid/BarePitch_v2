# View Public Livestream End to End

# Purpose
Implement the public livestream page (`GET /live/{token}`), the JSON polling endpoint (`GET /live/{token}/data`), the JavaScript polling loop, and correct expiration/stop handling on every request. The page shows current score, active phase, and event timeline. It updates automatically via polling and reflects corrections while the livestream is active.

# Required Context
See `01-shared-context.md`. Prompt 02 must be complete: `LivestreamService`, `LivestreamRepository`, `TokenGenerator`, and `LivestreamPolicy` must exist. The match-start and match-finish integrations must be in place.

# Required Documentation
- `docs/BarePitch-v2-05-critical-behavior-specifications-v1.0.md` — section 14 (Livestream Synchronization Behavior), specifically 14.2, 14.3, 14.4
- `docs/BarePitch-v2-08-route-api-specification-v1.0.md` — section 20 (`GET /live/{token}`, `GET /live/{token}/data`)
- `docs/BarePitch-v2-04-state-and-derived-data-policy-v1.0.md` — section 12 (Livestream Projection Policy)
- `docs/BarePitch-v2-10-data-integrity-test-scenarios-v1.0.md` — LS-02, LS-04

# Scope

## Livestream Data Repository Methods

Extend `app/Repositories/LivestreamRepository.php` with a method to load the public-safe match snapshot:

```php
/**
 * Load the public livestream data payload for a match.
 * Returns only data safe for public display — no private notes, no attendance, no ratings.
 *
 * @return array{match: array, score: array, phase: string, timeline: array}
 */
public function getPublicMatchSnapshot(int $matchId): array
{
    // Match row
    $matchStmt = $this->db->prepare(
        'SELECT m.id, m.opponent_name, m.home_away, m.active_phase,
                m.goals_scored, m.goals_conceded,
                m.shootout_goals_scored, m.shootout_goals_conceded,
                m.status
         FROM `match` m
         WHERE m.id = :match_id'
    );
    $matchStmt->execute([':match_id' => $matchId]);
    $match = $matchStmt->fetch(\PDO::FETCH_ASSOC);

    // Timeline events — public-visible event types only, excluding internal notes
    // note_text is only included for public-safe event types (not for note events)
    $eventsStmt = $this->db->prepare(
        'SELECT me.event_type, me.team_side, me.minute_display,
                me.outcome, me.zone_code,
                p.display_name AS scorer_name,
                ap.display_name AS assist_name
         FROM match_event me
         LEFT JOIN match_selection ms ON me.player_selection_id = ms.id
         LEFT JOIN player p ON ms.player_id = p.id
         LEFT JOIN match_selection ams ON me.assist_selection_id = ams.id
         LEFT JOIN player ap ON ams.player_id = ap.id
         WHERE me.match_id = :match_id
           AND me.event_type IN (\'goal\', \'penalty\', \'yellow_card\', \'red_card\')
         ORDER BY me.match_second ASC, me.id ASC'
    );
    $eventsStmt->execute([':match_id' => $matchId]);
    $events = $eventsStmt->fetchAll(\PDO::FETCH_ASSOC);

    // Substitutions for timeline
    $subsStmt = $this->db->prepare(
        'SELECT s.match_second,
                po.display_name AS player_off_name,
                pn.display_name AS player_on_name
         FROM substitution s
         LEFT JOIN match_selection mso ON s.player_off_selection_id = mso.id
         LEFT JOIN player po ON mso.player_id = po.id
         LEFT JOIN match_selection msn ON s.player_on_selection_id = msn.id
         LEFT JOIN player pn ON msn.player_id = pn.id
         WHERE s.match_id = :match_id
         ORDER BY s.match_second ASC, s.id ASC'
    );
    $subsStmt->execute([':match_id' => $matchId]);
    $substitutions = $subsStmt->fetchAll(\PDO::FETCH_ASSOC);

    return [
        'match'         => $match,
        'score'         => [
            'own'      => (int) $match['goals_scored'],
            'opponent' => (int) $match['goals_conceded'],
        ],
        'shootout_score' => [
            'own'      => (int) $match['shootout_goals_scored'],
            'opponent' => (int) $match['shootout_goals_conceded'],
        ],
        'phase'         => $match['active_phase'],
        'status'        => $match['status'],
        'timeline'      => $events,
        'substitutions' => $substitutions,
    ];
}
```

## GET /live/{token} — Public HTML Page

**Controller method**: `LivestreamController::showPublic(string $rawToken)`

Full implementation:

```php
public function showPublic(string $rawToken): void
{
    // Apply security headers before any output
    \App\Core\HttpHeaders::applyPublicLivestreamHeaders();

    // RATE LIMIT: integration point — see prompt 02 comment
    // RateLimiter::check('livestream_view', $_SERVER['REMOTE_ADDR'], limit: 60, windowSeconds: 60);

    $tokenRow = $this->livestreamService->validatePublicToken($rawToken);

    if ($tokenRow === null) {
        http_response_code(404);
        require APP_ROOT . '/app/Views/livestream/unavailable.php';
        return;
    }

    $matchId = (int) $tokenRow['match_id'];
    $snapshot = $this->livestreamRepository->getPublicMatchSnapshot($matchId);

    // Pass the raw token to the view so the polling JS can use it.
    // The raw token is already in the URL; including it in the page for JS is safe.
    require APP_ROOT . '/app/Views/livestream/show.php';
    // Variables available to view: $snapshot, $rawToken, $tokenRow
}
```

**View file**: `app/Views/livestream/show.php`

The view must:
- Display home team label or "our team" and opponent name
- Display current score prominently (e.g., `2 - 1`)
- Display current active phase in a human-readable label (e.g., "Half time", "Regular time", "Finished")
- Display timeline as a list of events sorted by match minute
- Display substitutions in the timeline with minute and player names
- Display shootout score if `active_phase` is `penalty_shootout` or if shootout data exists
- Include a `<div id="livestream-data">` or equivalent container that JavaScript updates on each poll
- Include the JavaScript polling script (see below)
- Not display: private notes, attendance, ratings, internal team data

Appropriate phase display labels:

| `active_phase` value | Display label |
|---|---|
| `none` | "Not started" |
| `regular_time` | "Regular time" |
| `halftime` | "Half time" |
| `extra_time` | "Extra time" |
| `penalty_shootout` | "Penalty shootout" |
| `finished` | "Finished" |

## GET /live/{token}/data — JSON Polling Endpoint

**Controller method**: `LivestreamController::showData(string $rawToken)`

```php
public function showData(string $rawToken): void
{
    // Apply security headers before any output
    \App\Core\HttpHeaders::applyPublicLivestreamHeaders();
    header('Content-Type: application/json; charset=utf-8');

    // RATE LIMIT: integration point
    // RateLimiter::check('livestream_data', $_SERVER['REMOTE_ADDR'], limit: 120, windowSeconds: 60);

    $tokenRow = $this->livestreamService->validatePublicToken($rawToken);

    if ($tokenRow === null) {
        http_response_code(404);
        echo json_encode([
            'ok' => false,
            'error' => ['code' => 'unavailable', 'message' => 'This livestream is not available.']
        ]);
        return;
    }

    $matchId = (int) $tokenRow['match_id'];
    $snapshot = $this->livestreamRepository->getPublicMatchSnapshot($matchId);

    echo json_encode([
        'ok'   => true,
        'data' => [
            'score'         => $snapshot['score'],
            'shootout_score' => $snapshot['shootout_score'],
            'phase'         => $snapshot['phase'],
            'status'        => $snapshot['status'],
            'timeline'      => $snapshot['timeline'],
            'substitutions' => $snapshot['substitutions'],
        ]
    ]);
}
```

**JSON success response shape**:
```json
{
  "ok": true,
  "data": {
    "score": { "own": 2, "opponent": 1 },
    "shootout_score": { "own": 0, "opponent": 0 },
    "phase": "regular_time",
    "status": "active",
    "timeline": [
      {
        "event_type": "goal",
        "team_side": "own",
        "minute_display": "23",
        "outcome": "scored",
        "zone_code": "tr",
        "scorer_name": "J. Bakker",
        "assist_name": "M. de Vries"
      }
    ],
    "substitutions": [
      {
        "match_second": 2700,
        "player_off_name": "A. Smit",
        "player_on_name": "B. Jansen"
      }
    ]
  }
}
```

**JSON failure response shape** (expired/stopped/invalid token):
```json
{
  "ok": false,
  "error": {
    "code": "unavailable",
    "message": "This livestream is not available."
  }
}
```

The failure response must be generic — it must not say "expired", "stopped", or "not found". HTTP status 404 for all token failure cases.

## JavaScript Polling

Embed the following inline script at the bottom of `app/Views/livestream/show.php`:

```html
<script>
(function () {
    'use strict';

    // Token is embedded in the URL — extract it for polling.
    // The data endpoint mirrors the current page path with /data appended.
    var dataUrl = window.location.pathname + '/data';
    var pollIntervalMs = 60000; // 60 seconds, per docs recommendation
    var pollTimer = null;
    var consecutiveFailures = 0;
    var maxConsecutiveFailures = 3;

    function updateTimeline(data) {
        // Find or rebuild the timeline container
        var container = document.getElementById('livestream-data');
        if (!container) return;

        // Update score
        var scoreEl = container.querySelector('[data-role="score"]');
        if (scoreEl) {
            scoreEl.textContent = data.score.own + ' – ' + data.score.opponent;
        }

        // Update phase label
        var phaseLabels = {
            'none': 'Not started',
            'regular_time': 'Regular time',
            'halftime': 'Half time',
            'extra_time': 'Extra time',
            'penalty_shootout': 'Penalty shootout',
            'finished': 'Finished'
        };
        var phaseEl = container.querySelector('[data-role="phase"]');
        if (phaseEl) {
            phaseEl.textContent = phaseLabels[data.phase] || data.phase;
        }

        // If match is finished and status = finished, stop polling
        if (data.status === 'finished') {
            stopPolling();
        }
    }

    function poll() {
        fetch(dataUrl, { method: 'GET', credentials: 'omit' })
            .then(function (response) {
                if (response.status === 404) {
                    // Livestream no longer available — stop polling, show unavailable message
                    stopPolling();
                    showUnavailable();
                    return null;
                }
                return response.json();
            })
            .then(function (json) {
                if (!json) return;
                if (json.ok) {
                    consecutiveFailures = 0;
                    updateTimeline(json.data);
                } else {
                    // Generic server-side failure
                    consecutiveFailures++;
                    if (consecutiveFailures >= maxConsecutiveFailures) {
                        stopPolling();
                        showUnavailable();
                    }
                }
            })
            .catch(function () {
                consecutiveFailures++;
                if (consecutiveFailures >= maxConsecutiveFailures) {
                    stopPolling();
                    // Do not show unavailable on network errors — let user retry manually
                }
            });
    }

    function stopPolling() {
        if (pollTimer !== null) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function showUnavailable() {
        var container = document.getElementById('livestream-data');
        if (container) {
            container.innerHTML = '<p class="livestream-unavailable">This livestream is no longer available.</p>';
        }
    }

    // Start polling after the initial page load
    if (typeof window.fetch !== 'undefined') {
        pollTimer = setInterval(poll, pollIntervalMs);
    }
    // If fetch is not available (very old browser), no polling — page content is static
})();
</script>
```

**Polling rules enforced by this implementation**:
- Interval: 60 seconds (per docs recommendation — section 14.2 of behavior spec)
- On HTTP 404 from data endpoint: stop polling, show unavailable message
- On `ok: false` from data endpoint: increment failure counter; stop after 3 consecutive failures
- On network error: increment failure counter; do not show unavailable immediately (network may be transient)
- When `status = finished` in polling response: stop polling (match is over, no more changes expected)
- `credentials: 'omit'` — the polling request sends no session cookies (public endpoint)

## Expiration and Stop Check on Each Request

Both `showPublic()` and `showData()` call `LivestreamService::validatePublicToken()` on every request. This method re-validates all conditions each time:

1. Token row exists (hash lookup)
2. `stopped_at IS NULL`
3. `expires_at IS NULL OR expires_at > NOW()`

There is no caching of validation results. Every request to a public livestream endpoint re-validates from the database. This ensures that a stopped or expired token takes effect on the next request without delay.

## Corrections Visible in Active Livestream

While the livestream is active, `getPublicMatchSnapshot()` reads the current cached score from `match.goals_scored` and `match.goals_conceded`. When a correction is applied (via `CorrectionService`, covered in prompt 07), `ScoreRecalculationService` updates these cached fields inside the same transaction. The next poll by the public page reads the corrected values automatically — no additional logic is required here.

This satisfies test scenario LS-04: a score corrected on a finished match with an active livestream appears in the next poll response.

## Unavailable View

`app/Views/livestream/unavailable.php`:

```php
<?php
// HTTP 404 status is set by the controller before requiring this view.
// This view must not reveal whether the token existed, expired, or was stopped.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Livestream Unavailable</title>
    <meta name="robots" content="noindex, nofollow">
</head>
<body>
    <main>
        <h1>This link is not available.</h1>
        <p>The livestream you are looking for is not available.</p>
    </main>
</body>
</html>
```

# Out of Scope
- Match edit locking (prompt 04)
- Finished match correction routes (prompt 07)
- Score recalculation service (prompt 05)
- Audit logging (prompt 07)
- Livestream token creation details (prompt 02)

# Architectural Rules
- `getPublicMatchSnapshot()` is in `LivestreamRepository` — it is a read-only scoped query, not a service concern
- The snapshot must never return private notes (`note_text` is excluded from the public event query), attendance data, or ratings
- Security headers are always applied before any output, including error views
- The polling JS uses `credentials: 'omit'` — no session cookie is sent to the public data endpoint
- Token re-validation happens on every request — no caching of validity in session or cookie

# Acceptance Criteria
- `GET /live/{validToken}` returns HTTP 200, sends `Cache-Control: no-store`, `Referrer-Policy: no-referrer`, `X-Robots-Tag: noindex, nofollow`
- Public page shows current score, phase label, and timeline events
- `GET /live/{validToken}/data` returns JSON with `ok: true` and correct score/phase/timeline
- `GET /live/{expiredToken}/data` returns HTTP 404 and `ok: false` with generic message
- `GET /live/{stoppedToken}/data` returns HTTP 404 and generic message — no detail about why
- Polling JS runs every 60 seconds and updates score and phase without page reload
- When data endpoint returns 404, polling stops and page shows unavailable message
- After a score correction on a finished match with active livestream, the next poll returns the corrected score (LS-04 scenario passes)
- No private notes, attendance data, or ratings appear in public responses

# Verification
- PHP syntax check all new/modified files
- Test `GET /live/{validToken}` manually: verify headers with browser dev tools or `curl -I`
- Test `GET /live/{validToken}/data`: verify JSON shape matches documented structure
- Test with expired token (manually set `expires_at` to past): verify generic 404
- Test with stopped token (manually set `stopped_at`): verify generic 404
- Test LS-04: finish a match, apply a correction, poll the data endpoint, verify corrected score appears
- Verify public response does not include `note_text` for any event type

# Handoff Note
`04-acquire-correction-lock-end-to-end.md` implements the lock table and acquisition logic required by all correction routes. `05-score-recalculation-after-correction.md` and `06-audit-logging.md` provide correction support services; `07-correct-finished-match-end-to-end.md` implements the correction routes that update the source data reflected by the polling endpoint.

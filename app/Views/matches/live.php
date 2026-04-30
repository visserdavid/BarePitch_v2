<?php
declare(strict_types=1);

// Variables provided via extract($data) or via show.php include:
// $match:   array  — match record (id, opponent_name, score_own, score_opponent, status)
// $team:    array  — active team
// $period:  array|null — current period row
// $events:  array  — event rows
// $players: array  — present player selections
// $errors:  array  — validation errors
$match   = isset($match)   && is_array($match)   ? $match   : [];
$team    = isset($team)    && is_array($team)     ? $team    : [];
$period  = isset($period)  && is_array($period)  ? $period  : null;
$events  = isset($events)  && is_array($events)  ? $events  : [];
$players = isset($players) && is_array($players) ? $players : [];
$errors  = isset($errors)  && is_array($errors)  ? $errors  : [];

$matchId       = (int)   ($match['id']              ?? 0);
$opponent      = (string)($match['opponent_name']   ?? '');
$scoreOwn      = (int)   ($match['score_own']       ?? 0);
$scoreOpponent = (int)   ($match['score_opponent']  ?? 0);
$teamName      = (string)($team['name']             ?? 'Us');

$periodLabel   = $period ? (string)($period['label'] ?? 'Period') : 'Not started';

?>
<!-- v0.1.0: only goal registration is implemented -->

<!-- ── Score bar ─────────────────────────── -->
<div class="bp-live-bar" style="margin-bottom:var(--s-4);">
  <div class="bp-team-name">
    <p class="t-tiny" style="margin:0 0 2px;">Home</p>
    <span><?= htmlspecialchars($teamName, ENT_QUOTES, 'UTF-8') ?></span>
  </div>

  <div class="bp-score">
    <span><?= htmlspecialchars((string)$scoreOwn, ENT_QUOTES, 'UTF-8') ?></span>
    <span style="color:var(--ink-3);font-size:20px;">–</span>
    <span><?= htmlspecialchars((string)$scoreOpponent, ENT_QUOTES, 'UTF-8') ?></span>
  </div>

  <div class="bp-team-name bp-team-name--away">
    <p class="t-tiny" style="margin:0 0 2px;text-align:right;">Away</p>
    <span><?= htmlspecialchars($opponent, ENT_QUOTES, 'UTF-8') ?></span>
  </div>
</div>

<!-- Period info -->
<div style="text-align:center;margin-bottom:var(--s-5);">
  <span class="chip chip-live"><?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') ?></span>
</div>

<?php if (!empty($errors)): ?>
  <div class="bp-flash__item bp-flash__item--error" role="alert" style="margin-bottom:var(--s-4);">
    <ul style="margin:0;padding-left:var(--s-4);">
      <?php foreach ($errors as $err): ?>
        <li><?= htmlspecialchars((string)$err, ENT_QUOTES, 'UTF-8') ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<!-- ── Period controls ─────────────────────────── -->
<div class="bp-section">
  <h2 class="t-h3" style="margin:0 0 var(--s-3);">Period controls</h2>
  <div class="bp-cluster">
    <?php if ($period === null): ?>
      <form method="post" action="/matches/<?= htmlspecialchars((string)$matchId, ENT_QUOTES, 'UTF-8') ?>/start">
        <?php include __DIR__ . '/../partials/csrf.php'; ?>
        <button type="submit" class="btn btn-primary">Start first half</button>
      </form>
    <?php else: ?>
      <?php $periodEnded = !empty($period['ended_at']); ?>
      <?php if (!$periodEnded): ?>
        <?php $periodId = (int)($period['id'] ?? 0); ?>
        <form method="post" action="/matches/<?= htmlspecialchars((string)$matchId, ENT_QUOTES, 'UTF-8') ?>/periods/<?= htmlspecialchars((string)$periodId, ENT_QUOTES, 'UTF-8') ?>/end">
          <?php include __DIR__ . '/../partials/csrf.php'; ?>
          <button type="submit" class="btn btn-secondary">End period</button>
        </form>
      <?php else: ?>
        <form method="post" action="/matches/<?= htmlspecialchars((string)$matchId, ENT_QUOTES, 'UTF-8') ?>/periods/start-second-half">
          <?php include __DIR__ . '/../partials/csrf.php'; ?>
          <button type="submit" class="btn btn-primary">Start next half</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<!-- ── Goal registration ─────────────────────────── -->
<div class="bp-section">
  <h2 class="t-h3" style="margin:0 0 var(--s-4);">Register event</h2>

  <!-- Toggle buttons — goals only in v0.1.0 -->
  <div class="bp-cluster" style="margin-bottom:var(--s-4);">
    <button
      type="button"
      class="btn btn-secondary btn-sm"
      onclick="document.querySelectorAll('.event-form').forEach(el=>el.hidden=true);document.getElementById('form-goal-own').hidden=false;"
      aria-controls="form-goal-own"
    >
      + Goal (<?= htmlspecialchars($teamName, ENT_QUOTES, 'UTF-8') ?>)
    </button>
    <button
      type="button"
      class="btn btn-secondary btn-sm"
      onclick="document.querySelectorAll('.event-form').forEach(el=>el.hidden=true);document.getElementById('form-goal-opponent').hidden=false;"
      aria-controls="form-goal-opponent"
    >
      + Goal (<?= htmlspecialchars($opponent, ENT_QUOTES, 'UTF-8') ?>)
    </button>
  </div>

  <!-- v0.1.0: cards/notes not yet implemented -->

  <!-- Own-goal form -->
  <div id="form-goal-own" class="event-form bp-card" hidden>
    <h3 class="t-h3" style="margin:0 0 var(--s-4);">Goal — <?= htmlspecialchars($teamName, ENT_QUOTES, 'UTF-8') ?></h3>
    <form method="post" action="/matches/<?= htmlspecialchars((string)$matchId, ENT_QUOTES, 'UTF-8') ?>/events/goal" class="bp-stack">
      <?php include __DIR__ . '/../partials/csrf.php'; ?>
      <input type="hidden" name="team_side" value="own">

      <!-- Player picker (required for own-team goals) -->
      <div class="field">
        <label for="player_selection_id_own">Player <span class="req" aria-hidden="true">*</span></label>
        <select
          id="player_selection_id_own"
          name="player_selection_id"
          class="input"
          required
        >
          <option value="">— select player —</option>
          <?php foreach ($players as $p): ?>
            <?php
            $pid   = (int)   ($p['id']           ?? 0);
            $pName = (string)($p['name']          ?? '');
            $pNum  = (string)($p['shirt_number']  ?? '');
            ?>
            <option value="<?= htmlspecialchars((string)$pid, ENT_QUOTES, 'UTF-8') ?>">
              <?= htmlspecialchars($pNum !== '' ? "#{$pNum} {$pName}" : $pName, ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Assist picker (optional) -->
      <div class="field">
        <label for="assist_selection_id_own">Assist <span class="muted">(optional)</span></label>
        <select
          id="assist_selection_id_own"
          name="assist_selection_id"
          class="input"
        >
          <option value="">— none —</option>
          <?php foreach ($players as $p): ?>
            <?php
            $pid   = (int)   ($p['id']           ?? 0);
            $pName = (string)($p['name']          ?? '');
            $pNum  = (string)($p['shirt_number']  ?? '');
            ?>
            <option value="<?= htmlspecialchars((string)$pid, ENT_QUOTES, 'UTF-8') ?>">
              <?= htmlspecialchars($pNum !== '' ? "#{$pNum} {$pName}" : $pName, ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Zone (optional) -->
      <div class="field">
        <label for="zone_code_own">Zone <span class="muted">(optional)</span></label>
        <input
          id="zone_code_own"
          type="text"
          name="zone_code"
          class="input"
          maxlength="20"
          placeholder="e.g. box"
        >
      </div>

      <!-- Minute (required) -->
      <div class="field">
        <label for="minute_display_own">Minute <span class="req" aria-hidden="true">*</span></label>
        <input
          id="minute_display_own"
          type="number"
          name="minute_display"
          class="input"
          min="1"
          max="200"
          placeholder="e.g. 23"
          required
        >
      </div>

      <div class="bp-cluster" style="justify-content:flex-end;">
        <button type="button" class="btn btn-ghost btn-sm"
          onclick="document.getElementById('form-goal-own').hidden=true;">
          Cancel
        </button>
        <button type="submit" class="btn btn-primary btn-sm">Save goal</button>
      </div>
    </form>
  </div>

  <!-- Opponent-goal form -->
  <div id="form-goal-opponent" class="event-form bp-card" hidden>
    <h3 class="t-h3" style="margin:0 0 var(--s-4);">Goal — <?= htmlspecialchars($opponent, ENT_QUOTES, 'UTF-8') ?></h3>
    <form method="post" action="/matches/<?= htmlspecialchars((string)$matchId, ENT_QUOTES, 'UTF-8') ?>/events/goal" class="bp-stack">
      <?php include __DIR__ . '/../partials/csrf.php'; ?>
      <input type="hidden" name="team_side" value="opponent">

      <!-- Player picker (optional for opponent goals) -->
      <div class="field">
        <label for="player_selection_id_opponent">Player <span class="muted">(optional)</span></label>
        <select
          id="player_selection_id_opponent"
          name="player_selection_id"
          class="input"
        >
          <option value="">— unknown —</option>
          <?php foreach ($players as $p): ?>
            <?php
            $pid   = (int)   ($p['id']           ?? 0);
            $pName = (string)($p['name']          ?? '');
            $pNum  = (string)($p['shirt_number']  ?? '');
            ?>
            <option value="<?= htmlspecialchars((string)$pid, ENT_QUOTES, 'UTF-8') ?>">
              <?= htmlspecialchars($pNum !== '' ? "#{$pNum} {$pName}" : $pName, ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Assist picker (optional) -->
      <div class="field">
        <label for="assist_selection_id_opponent">Assist <span class="muted">(optional)</span></label>
        <select
          id="assist_selection_id_opponent"
          name="assist_selection_id"
          class="input"
        >
          <option value="">— none —</option>
          <?php foreach ($players as $p): ?>
            <?php
            $pid   = (int)   ($p['id']           ?? 0);
            $pName = (string)($p['name']          ?? '');
            $pNum  = (string)($p['shirt_number']  ?? '');
            ?>
            <option value="<?= htmlspecialchars((string)$pid, ENT_QUOTES, 'UTF-8') ?>">
              <?= htmlspecialchars($pNum !== '' ? "#{$pNum} {$pName}" : $pName, ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Zone (optional) -->
      <div class="field">
        <label for="zone_code_opponent">Zone <span class="muted">(optional)</span></label>
        <input
          id="zone_code_opponent"
          type="text"
          name="zone_code"
          class="input"
          maxlength="20"
          placeholder="e.g. box"
        >
      </div>

      <!-- Minute (required) -->
      <div class="field">
        <label for="minute_display_opponent">Minute <span class="req" aria-hidden="true">*</span></label>
        <input
          id="minute_display_opponent"
          type="number"
          name="minute_display"
          class="input"
          min="1"
          max="200"
          placeholder="e.g. 23"
          required
        >
      </div>

      <div class="bp-cluster" style="justify-content:flex-end;">
        <button type="button" class="btn btn-ghost btn-sm"
          onclick="document.getElementById('form-goal-opponent').hidden=true;">
          Cancel
        </button>
        <button type="submit" class="btn btn-primary btn-sm">Save goal</button>
      </div>
    </form>
  </div>
</div>

<!-- ── Event timeline ─────────────────────────── -->
<div class="bp-section">
  <h2 class="t-h3" style="margin:0 0 var(--s-4);">Events</h2>

  <?php if (empty($events)): ?>
    <div class="bp-card bp-card--flat" style="text-align:center;padding:var(--s-5);">
      <p class="t-small muted" style="margin:0;">No events yet.</p>
    </div>
  <?php else: ?>
    <div class="timeline bp-card" style="padding:0;overflow:hidden;">
      <?php foreach ($events as $event): ?>
        <?php
        $evId     = (int)   ($event['id']         ?? 0);
        $evType   = (string)($event['event_type'] ?? '');
        $evMin    = isset($event['minute']) ? (int)$event['minute'] : null;
        $evSide   = (string)($event['team_side']  ?? '');
        $evPlayer = (string)($event['player_name'] ?? '');
        $evNote   = (string)($event['note']        ?? '');

        $iconClass = match($evType) {
            'goal', 'penalty' => 'goal',
            'yellow_card'     => 'yellow',
            'red_card'        => 'red',
            default           => '',
        };
        $evLabel = match($evType) {
            'goal'        => 'Goal',
            'penalty'     => 'Penalty',
            'yellow_card' => 'Yellow card',
            'red_card'    => 'Red card',
            'note'        => 'Note',
            default       => $evType,
        };
        $sideLabel = ($evSide === 'own') ? $teamName : $opponent;
        ?>
        <div class="tl-row">
          <span class="tl-min"><?= $evMin !== null ? htmlspecialchars((string)$evMin . "'", ENT_QUOTES, 'UTF-8') : '–' ?></span>
          <span class="tl-icon <?= htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true">
            <?php if ($evType === 'yellow_card'): ?>
              <span class="cardflag yellow"></span>
            <?php elseif ($evType === 'red_card'): ?>
              <span class="cardflag red"></span>
            <?php else: ?>
              <svg class="bp-icon" aria-hidden="true"><use href="/assets/barepitch-icons.svg#bp-icon-ball"></use></svg>
            <?php endif; ?>
          </span>
          <span class="tl-event">
            <span class="who">
              <?= $evPlayer !== '' ? htmlspecialchars($evPlayer, ENT_QUOTES, 'UTF-8') : htmlspecialchars($sideLabel, ENT_QUOTES, 'UTF-8') ?>
            </span>
            <span class="what">
              <?= htmlspecialchars($evLabel, ENT_QUOTES, 'UTF-8') ?>
              <?= $evSide !== '' ? '· ' . htmlspecialchars($sideLabel, ENT_QUOTES, 'UTF-8') : '' ?>
              <?= $evNote !== '' ? '· ' . htmlspecialchars($evNote, ENT_QUOTES, 'UTF-8') : '' ?>
            </span>
          </span>
          <form method="post" action="/matches/<?= htmlspecialchars((string)$matchId, ENT_QUOTES, 'UTF-8') ?>/events/<?= htmlspecialchars((string)$evId, ENT_QUOTES, 'UTF-8') ?>/delete">
            <?php include __DIR__ . '/../partials/csrf.php'; ?>
            <button type="submit" class="tl-edit" title="Delete event" onclick="return confirm('Delete this event?');">
              <svg class="bp-icon" aria-hidden="true"><use href="/assets/barepitch-icons.svg#bp-icon-trash"></use></svg>
            </button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- ── Finish match ─────────────────────────── -->
<div class="bp-section" style="border-top:1px solid var(--line);padding-top:var(--s-5);">
  <form method="post" action="/matches/<?= htmlspecialchars((string)$matchId, ENT_QUOTES, 'UTF-8') ?>/finish"
        onsubmit="return confirm('Are you sure you want to finish this match? This cannot be undone.');">
    <?php include __DIR__ . '/../partials/csrf.php'; ?>
    <button type="submit" class="btn btn-danger btn-lg btn-block">Finish match</button>
    <p class="t-tiny" style="text-align:center;margin-top:var(--s-3);color:var(--ink-3);">
      This will end the match and lock all events. The action cannot be reversed.
    </p>
  </form>
</div>

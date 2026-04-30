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

$eventTypes = [
    'goal'        => ['label' => 'Goal',        'icon' => 'goal'],
    'penalty'     => ['label' => 'Penalty',     'icon' => 'goal'],
    'yellow_card' => ['label' => 'Yellow card', 'icon' => 'card'],
    'red_card'    => ['label' => 'Red card',    'icon' => 'card'],
    'note'        => ['label' => 'Note',        'icon' => 'note'],
];
?>

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
      <form method="post" action="/matches/<?= htmlspecialchars((string)$matchId, ENT_QUOTES, 'UTF-8') ?>/period/start">
        <?php include __DIR__ . '/../partials/csrf.php'; ?>
        <button type="submit" class="btn btn-primary">Start first half</button>
      </form>
    <?php else: ?>
      <?php $periodEnded = !empty($period['ended_at']); ?>
      <?php if (!$periodEnded): ?>
        <form method="post" action="/matches/<?= htmlspecialchars((string)$matchId, ENT_QUOTES, 'UTF-8') ?>/period/end">
          <?php include __DIR__ . '/../partials/csrf.php'; ?>
          <button type="submit" class="btn btn-secondary">End period</button>
        </form>
      <?php else: ?>
        <form method="post" action="/matches/<?= htmlspecialchars((string)$matchId, ENT_QUOTES, 'UTF-8') ?>/period/start">
          <?php include __DIR__ . '/../partials/csrf.php'; ?>
          <button type="submit" class="btn btn-primary">Start next half</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<!-- ── Goal / Event registration ─────────────────────────── -->
<div class="bp-section">
  <h2 class="t-h3" style="margin:0 0 var(--s-4);">Register event</h2>

  <!-- Toggle buttons for each event type -->
  <div class="bp-cluster" style="margin-bottom:var(--s-4);">
    <?php foreach ($eventTypes as $typeKey => $typeData): ?>
      <button
        type="button"
        class="btn btn-secondary btn-sm"
        onclick="document.querySelectorAll('.event-form').forEach(el=>el.hidden=true);document.getElementById('form-<?= htmlspecialchars($typeKey, ENT_QUOTES, 'UTF-8') ?>').hidden=false;"
        aria-controls="form-<?= htmlspecialchars($typeKey, ENT_QUOTES, 'UTF-8') ?>"
      >
        + <?= htmlspecialchars($typeData['label'], ENT_QUOTES, 'UTF-8') ?>
      </button>
    <?php endforeach; ?>
  </div>

  <!-- Inline event forms (JS-toggled) -->
  <?php foreach ($eventTypes as $typeKey => $typeData): ?>
    <div id="form-<?= htmlspecialchars($typeKey, ENT_QUOTES, 'UTF-8') ?>" class="event-form bp-card" hidden>
      <h3 class="t-h3" style="margin:0 0 var(--s-4);"><?= htmlspecialchars($typeData['label'], ENT_QUOTES, 'UTF-8') ?></h3>
      <form method="post" action="/matches/<?= htmlspecialchars((string)$matchId, ENT_QUOTES, 'UTF-8') ?>/events" class="bp-stack">
        <?php include __DIR__ . '/../partials/csrf.php'; ?>
        <input type="hidden" name="event_type" value="<?= htmlspecialchars($typeKey, ENT_QUOTES, 'UTF-8') ?>">

        <!-- Side (own/opponent) — only for score events and cards -->
        <?php if (in_array($typeKey, ['goal', 'penalty', 'yellow_card', 'red_card'], true)): ?>
          <div class="field">
            <label>Side</label>
            <div class="bp-cluster">
              <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                <input type="radio" name="team_side" value="own" checked style="accent-color:var(--accent);">
                <span class="t-small"><?= htmlspecialchars($teamName, ENT_QUOTES, 'UTF-8') ?></span>
              </label>
              <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                <input type="radio" name="team_side" value="opponent" style="accent-color:var(--accent);">
                <span class="t-small"><?= htmlspecialchars($opponent, ENT_QUOTES, 'UTF-8') ?></span>
              </label>
            </div>
          </div>
        <?php endif; ?>

        <!-- Player picker (own team only — shown for goal/penalty/cards) -->
        <?php if (in_array($typeKey, ['goal', 'penalty', 'yellow_card', 'red_card'], true) && !empty($players)): ?>
          <div class="field">
            <label for="player_id_<?= htmlspecialchars($typeKey, ENT_QUOTES, 'UTF-8') ?>">Player <span class="muted">(optional for own team)</span></label>
            <select
              id="player_id_<?= htmlspecialchars($typeKey, ENT_QUOTES, 'UTF-8') ?>"
              name="player_id"
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
        <?php endif; ?>

        <!-- Minute -->
        <div class="field">
          <label for="minute_<?= htmlspecialchars($typeKey, ENT_QUOTES, 'UTF-8') ?>">Minute</label>
          <input
            id="minute_<?= htmlspecialchars($typeKey, ENT_QUOTES, 'UTF-8') ?>"
            type="number"
            name="minute"
            class="input"
            min="1"
            max="200"
            placeholder="e.g. 23"
          >
        </div>

        <!-- Note text (always shown for note type, optional for others) -->
        <?php if ($typeKey === 'note'): ?>
          <div class="field">
            <label for="note_<?= htmlspecialchars($typeKey, ENT_QUOTES, 'UTF-8') ?>">Note</label>
            <textarea
              id="note_<?= htmlspecialchars($typeKey, ENT_QUOTES, 'UTF-8') ?>"
              name="note"
              class="input"
              maxlength="500"
              placeholder="Describe the event..."
            ></textarea>
          </div>
        <?php endif; ?>

        <div class="bp-cluster" style="justify-content:flex-end;">
          <button type="button" class="btn btn-ghost btn-sm"
            onclick="document.getElementById('form-<?= htmlspecialchars($typeKey, ENT_QUOTES, 'UTF-8') ?>').hidden=true;">
            Cancel
          </button>
          <button type="submit" class="btn btn-primary btn-sm">Save event</button>
        </div>
      </form>
    </div>
  <?php endforeach; ?>
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

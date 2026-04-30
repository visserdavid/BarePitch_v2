<?php
declare(strict_types=1);

// Variables provided via extract($data) or via show.php include:
// $match:      array  — match record
// $team:       array  — active team
// $players:    array  — all squad players
// $selections: array  — existing attendance selections keyed by player_id
// $formations: array  — available formation options (id, name, slots)
// $lineupSlots:array  — existing lineup slot assignments
// $errors:     array  — validation errors
$match       = isset($match)       && is_array($match)       ? $match       : [];
$team        = isset($team)        && is_array($team)        ? $team        : [];
$players     = isset($players)     && is_array($players)     ? $players     : [];
$selections  = isset($selections)  && is_array($selections)  ? $selections  : [];
$formations  = isset($formations)  && is_array($formations)  ? $formations  : [];
$lineupSlots = isset($lineupSlots) && is_array($lineupSlots) ? $lineupSlots : [];
$errors      = isset($errors)      && is_array($errors)      ? $errors      : [];

$matchId  = (int)   ($match['id']            ?? 0);
$opponent = (string)($match['opponent_name'] ?? '');
$status   = (string)($match['status']        ?? 'planned');

// Build an index: player_id → selection row
$selByPlayer = [];
foreach ($selections as $sel) {
    $pid = (int)($sel['player_id'] ?? 0);
    $selByPlayer[$pid] = $sel;
}

// Build an index: slot_position → lineup slot row
$slotByPosition = [];
foreach ($lineupSlots as $slot) {
    $pos = (string)($slot['position'] ?? '');
    $slotByPosition[$pos] = $slot;
}

$isConfirmed = ($status === 'prepared');
?>

<!-- ── Header ─────────────────────────── -->
<div class="bp-screen-head">
  <div>
    <p class="bp-screen-kicker"><?= htmlspecialchars((string)($team['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
    <h1 class="bp-screen-title">
      vs <?= htmlspecialchars($opponent, ENT_QUOTES, 'UTF-8') ?>
    </h1>
  </div>
  <span class="chip <?= $isConfirmed ? 'chip-accent' : '' ?>">
    <?= htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8') ?>
  </span>
</div>

<?php if (!empty($errors)): ?>
  <div class="bp-flash__item bp-flash__item--error" role="alert" style="margin-top:var(--s-4);">
    <ul style="margin:0;padding-left:var(--s-4);">
      <?php foreach ($errors as $err): ?>
        <li><?= htmlspecialchars((string)$err, ENT_QUOTES, 'UTF-8') ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<!-- ── Section 1: Preparation checklist ─────────────────────────── -->
<div class="bp-section">
  <h2 class="t-h3" style="margin:0 0 var(--s-4);">Checklist</h2>
  <div class="checklist bp-card" style="padding:0;overflow:hidden;">
    <?php
    $attendanceSet  = !empty($selByPlayer);
    $formationSet   = !empty($lineupSlots);
    $checkItems = [
        ['label' => 'Set attendance',    'done' => $attendanceSet],
        ['label' => 'Set formation',     'done' => $formationSet],
    ];
    foreach ($checkItems as $item):
        $rowClass = $item['done'] ? 'row done' : 'row';
    ?>
      <div class="<?= $rowClass ?>">
        <span class="check" aria-hidden="true">
          <?php if ($item['done']): ?>
            <svg width="10" height="10" viewBox="0 0 10 10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="2,5 4,7.5 8,2.5"/></svg>
          <?php endif; ?>
        </span>
        <span class="label"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
        <span class="meta"><?= $item['done'] ? 'Done' : 'Pending' ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ── Section 2: Attendance ─────────────────────────── -->
<div class="bp-section">
  <h2 class="t-h3" style="margin:0 0 var(--s-4);">Attendance</h2>

  <?php if (empty($players)): ?>
    <p class="t-small" style="color:var(--ink-3);">No players in squad. <a href="/players/create">Add players first.</a></p>
  <?php else: ?>
    <form method="post" action="/matches/<?= htmlspecialchars((string)$matchId, ENT_QUOTES, 'UTF-8') ?>/attendance" class="bp-stack">
      <?php include __DIR__ . '/../partials/csrf.php'; ?>

      <div class="bp-card" style="padding:0;overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;">
          <thead>
            <tr style="border-bottom:1px solid var(--line);">
              <th style="padding:var(--s-3) var(--s-4);text-align:left;" class="t-tiny">#</th>
              <th style="padding:var(--s-3) var(--s-4);text-align:left;" class="t-tiny">Player</th>
              <th style="padding:var(--s-3) var(--s-4);text-align:left;" class="t-tiny">Position</th>
              <th style="padding:var(--s-3) var(--s-4);text-align:center;" class="t-tiny">Attendance</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($players as $player): ?>
              <?php
              $pid      = (int)   ($player['id']       ?? 0);
              $pName    = (string)($player['name']      ?? '');
              $pShirt   = (string)($player['shirt_number'] ?? '–');
              $pPos     = (string)($player['position']  ?? '');
              $existing = $selByPlayer[$pid] ?? null;
              $current  = $existing ? (string)($existing['status'] ?? '') : '';
              $statuses = ['present', 'absent', 'unknown'];
              ?>
              <tr style="border-bottom:1px solid var(--line);">
                <td style="padding:var(--s-3) var(--s-4);" class="t-num t-small"><?= htmlspecialchars($pShirt, ENT_QUOTES, 'UTF-8') ?></td>
                <td style="padding:var(--s-3) var(--s-4);font-weight:600;font-size:14px;"><?= htmlspecialchars($pName, ENT_QUOTES, 'UTF-8') ?></td>
                <td style="padding:var(--s-3) var(--s-4);" class="t-small muted"><?= htmlspecialchars(ucfirst($pPos), ENT_QUOTES, 'UTF-8') ?></td>
                <td style="padding:var(--s-3) var(--s-4);text-align:center;">
                  <div style="display:flex;align-items:center;justify-content:center;gap:var(--s-3);">
                    <?php foreach ($statuses as $s): ?>
                      <label style="display:flex;align-items:center;gap:4px;cursor:pointer;">
                        <input
                          type="radio"
                          name="attendance[<?= htmlspecialchars((string)$pid, ENT_QUOTES, 'UTF-8') ?>]"
                          value="<?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?>"
                          <?= ($current === $s || ($current === '' && $s === 'unknown')) ? 'checked' : '' ?>
                          style="accent-color:var(--accent);"
                        >
                        <span class="t-tiny" style="text-transform:capitalize;"><?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div style="text-align:right;">
        <button type="submit" class="btn btn-secondary btn-sm">Save attendance</button>
      </div>
    </form>
  <?php endif; ?>
</div>

<!-- ── Section 3: Formation + Lineup ─────────────────────────── -->
<div class="bp-section">
  <h2 class="t-h3" style="margin:0 0 var(--s-4);">Formation &amp; Lineup</h2>

  <form method="post" action="/matches/<?= htmlspecialchars((string)$matchId, ENT_QUOTES, 'UTF-8') ?>/lineup" class="bp-stack">
    <?php include __DIR__ . '/../partials/csrf.php'; ?>

    <?php if (!empty($formations)): ?>
      <div class="field">
        <label for="formation_id">Formation</label>
        <select id="formation_id" name="formation_id" class="input">
          <option value="">— choose —</option>
          <?php
          $currentFormationId = (int)($match['formation_id'] ?? 0);
          foreach ($formations as $f):
            $fId   = (int)   ($f['id']   ?? 0);
            $fName = (string)($f['name'] ?? '');
            $sel   = ($currentFormationId === $fId) ? 'selected' : '';
          ?>
            <option value="<?= htmlspecialchars((string)$fId, ENT_QUOTES, 'UTF-8') ?>" <?= $sel ?>>
              <?= htmlspecialchars($fName, ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>

    <!-- Lineup grid: position slots -->
    <?php
    $presentPlayers = array_filter($players, static fn($p) => (($selByPlayer[(int)($p['id'] ?? 0)]['status'] ?? '') === 'present'));
    $presentPlayers = array_values($presentPlayers);
    ?>

    <?php if (!empty($lineupSlots)): ?>
      <div class="bp-stack" style="gap:var(--s-2);">
        <p class="t-tiny" style="margin:0;">Assign players to positions</p>
        <?php foreach ($lineupSlots as $slot): ?>
          <?php
          $slotId  = (int)   ($slot['id']       ?? 0);
          $slotPos = (string)($slot['position']  ?? '');
          $slotLbl = (string)($slot['label']     ?? $slotPos);
          $slotPid = (int)   ($slot['player_id'] ?? 0);
          ?>
          <div class="field" style="flex-direction:row;align-items:center;gap:var(--s-3);">
            <label for="slot_<?= htmlspecialchars((string)$slotId, ENT_QUOTES, 'UTF-8') ?>"
                   style="min-width:80px;flex-shrink:0;" class="t-small">
              <?= htmlspecialchars($slotLbl, ENT_QUOTES, 'UTF-8') ?>
            </label>
            <select
              id="slot_<?= htmlspecialchars((string)$slotId, ENT_QUOTES, 'UTF-8') ?>"
              name="lineup[<?= htmlspecialchars((string)$slotId, ENT_QUOTES, 'UTF-8') ?>]"
              class="input"
              style="flex:1;"
            >
              <option value="">— unassigned —</option>
              <?php foreach ($presentPlayers as $pp): ?>
                <?php
                $ppId   = (int)   ($pp['id']   ?? 0);
                $ppName = (string)($pp['name'] ?? '');
                $ppNum  = (string)($pp['shirt_number'] ?? '');
                $ppSel  = ($slotPid === $ppId) ? 'selected' : '';
                ?>
                <option value="<?= htmlspecialchars((string)$ppId, ENT_QUOTES, 'UTF-8') ?>" <?= $ppSel ?>>
                  <?= htmlspecialchars($ppNum !== '' ? "#{$ppNum} {$ppName}" : $ppName, ENT_QUOTES, 'UTF-8') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endforeach; ?>
      </div>
    <?php elseif (empty($formations)): ?>
      <p class="t-small muted">Select a formation to set up lineup slots.</p>
    <?php endif; ?>

    <div style="text-align:right;">
      <button type="submit" class="btn btn-secondary btn-sm">Save lineup</button>
    </div>
  </form>
</div>

<!-- ── Confirm Preparation ─────────────────────────── -->
<?php if (!$isConfirmed): ?>
<div class="bp-section" style="border-top:1px solid var(--line);padding-top:var(--s-5);">
  <form method="post" action="/matches/<?= htmlspecialchars((string)$matchId, ENT_QUOTES, 'UTF-8') ?>/prepare">
    <?php include __DIR__ . '/../partials/csrf.php'; ?>
    <button type="submit" class="btn btn-primary btn-lg btn-block">
      Confirm preparation
    </button>
    <p class="t-tiny" style="text-align:center;margin-top:var(--s-3);color:var(--ink-3);">
      This locks attendance and lineup and marks the match as prepared.
    </p>
  </form>
</div>
<?php else: ?>
<div class="bp-section" style="border-top:1px solid var(--line);padding-top:var(--s-5);">
  <form method="post" action="/matches/<?= htmlspecialchars((string)$matchId, ENT_QUOTES, 'UTF-8') ?>/start">
    <?php include __DIR__ . '/../partials/csrf.php'; ?>
    <button type="submit" class="btn btn-ink btn-lg btn-block">
      Start match
    </button>
  </form>
</div>
<?php endif; ?>

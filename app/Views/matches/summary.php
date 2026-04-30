<?php
declare(strict_types=1);

// Variables provided via extract($data) or via show.php include:
// $match:      array — match record
// $team:       array — active team
// $events:     array — event rows
// $lineupSlots:array — starting lineup slot rows
$match       = isset($match)       && is_array($match)       ? $match       : [];
$team        = isset($team)        && is_array($team)        ? $team        : [];
$events      = isset($events)      && is_array($events)      ? $events      : [];
$lineupSlots = isset($lineupSlots) && is_array($lineupSlots) ? $lineupSlots : [];

$matchId       = (int)   ($match['id']              ?? 0);
$opponent      = (string)($match['opponent_name']   ?? '');
$scoreOwn      = (int)   ($match['goals_scored']    ?? 0);
$scoreOpponent = (int)   ($match['goals_conceded']  ?? 0);
$matchDate     = (string)($match['match_date']      ?? '');
$teamName      = (string)($team['name']             ?? 'Us');
?>

<div class="bp-screen-head">
  <div>
    <p class="bp-screen-kicker"><?= htmlspecialchars($teamName, ENT_QUOTES, 'UTF-8') ?></p>
    <h1 class="bp-screen-title">
      vs <?= htmlspecialchars($opponent, ENT_QUOTES, 'UTF-8') ?>
    </h1>
    <?php if ($matchDate !== ''): ?>
      <p class="t-small muted" style="margin:var(--s-1) 0 0;"><?= htmlspecialchars($matchDate, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
  </div>
  <span class="chip">Finished</span>
</div>

<!-- ── Final score ─────────────────────────── -->
<div class="bp-section">
  <div class="bp-live-bar" style="padding:var(--s-5);">
    <div class="bp-team-name">
      <p class="t-tiny" style="margin:0 0 4px;">Home</p>
      <span><?= htmlspecialchars($teamName, ENT_QUOTES, 'UTF-8') ?></span>
    </div>

    <div class="bp-score" style="font-size:48px;">
      <span><?= htmlspecialchars((string)$scoreOwn, ENT_QUOTES, 'UTF-8') ?></span>
      <span style="color:var(--ink-3);font-size:28px;">–</span>
      <span><?= htmlspecialchars((string)$scoreOpponent, ENT_QUOTES, 'UTF-8') ?></span>
    </div>

    <div class="bp-team-name bp-team-name--away">
      <p class="t-tiny" style="margin:0 0 4px;text-align:right;">Away</p>
      <span><?= htmlspecialchars($opponent, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
  </div>
</div>

<!-- ── Event timeline ─────────────────────────── -->
<div class="bp-section">
  <h2 class="t-h3" style="margin:0 0 var(--s-4);">Match events</h2>

  <?php if (empty($events)): ?>
    <div class="bp-card bp-card--flat" style="text-align:center;padding:var(--s-5);">
      <p class="t-small muted" style="margin:0;">No events recorded.</p>
    </div>
  <?php else: ?>
    <div class="timeline bp-card" style="padding:0;overflow:hidden;">
      <?php foreach ($events as $event): ?>
        <?php
        $evType   = (string)($event['event_type']  ?? '');
        $evMin    = isset($event['minute']) ? (int)$event['minute'] : null;
        $evSide   = (string)($event['team_side']   ?? '');
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
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- ── Starting lineup ─────────────────────────── -->
<?php if (!empty($lineupSlots)): ?>
<div class="bp-section">
  <h2 class="t-h3" style="margin:0 0 var(--s-4);">Starting lineup</h2>

  <div class="bp-card" style="padding:0;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;">
      <thead>
        <tr style="border-bottom:1px solid var(--line);">
          <th class="t-tiny" style="padding:var(--s-3) var(--s-4);text-align:left;">Position</th>
          <th class="t-tiny" style="padding:var(--s-3) var(--s-4);text-align:left;">Player</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($lineupSlots as $slot): ?>
          <?php
          $slotLabel  = (string)($slot['label']       ?? $slot['position'] ?? '');
          $playerName = (string)($slot['player_name'] ?? '–');
          $shirtNum   = (string)($slot['shirt_number'] ?? '');
          ?>
          <tr style="border-bottom:1px solid var(--line);">
            <td class="t-small" style="padding:var(--s-3) var(--s-4);color:var(--ink-3);">
              <?= htmlspecialchars($slotLabel, ENT_QUOTES, 'UTF-8') ?>
            </td>
            <td style="padding:var(--s-3) var(--s-4);font-weight:600;font-size:14px;">
              <?php if ($shirtNum !== ''): ?>
                <span class="t-num" style="color:var(--ink-3);margin-right:6px;">#<?= htmlspecialchars($shirtNum, ENT_QUOTES, 'UTF-8') ?></span>
              <?php endif; ?>
              <?= htmlspecialchars($playerName, ENT_QUOTES, 'UTF-8') ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div style="margin-top:var(--s-5);">
  <a href="/matches" class="btn btn-ghost btn-sm">← All matches</a>
</div>

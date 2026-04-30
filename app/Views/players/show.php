<?php
declare(strict_types=1);

// $player:       array      — player record
// $seasonContext:array|null — current season stats (goals, cards, appearances, etc.)
// $matchHistory: array      — array of match participation rows
$player        = isset($player)        && is_array($player)        ? $player        : [];
$seasonContext = isset($seasonContext) && is_array($seasonContext) ? $seasonContext : null;
$matchHistory  = isset($matchHistory) && is_array($matchHistory)  ? $matchHistory  : [];

$playerId  = (int)   ($player['id']           ?? 0);
$name      = (string)($player['name']          ?? '');
$shirtNum  = (string)($player['shirt_number']  ?? '');
$position  = (string)($player['position']      ?? '');
?>

<div class="bp-screen-head">
  <div>
    <p class="bp-screen-kicker">Player</p>
    <h1 class="bp-screen-title">
      <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>
    </h1>
    <div class="bp-cluster" style="margin-top:var(--s-2);">
      <?php if ($shirtNum !== ''): ?>
        <span class="chip">#<?= htmlspecialchars($shirtNum, ENT_QUOTES, 'UTF-8') ?></span>
      <?php endif; ?>
      <?php if ($position !== ''): ?>
        <span class="chip"><?= htmlspecialchars(ucfirst($position), ENT_QUOTES, 'UTF-8') ?></span>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ── Season stats ─────────────────────────── -->
<?php if ($seasonContext !== null): ?>
<div class="bp-section">
  <h2 class="t-h3" style="margin:0 0 var(--s-4);">Season overview</h2>
  <div class="stat-grid">
    <?php
    $stats = [
        'Appearances' => (int)($seasonContext['appearances'] ?? 0),
        'Goals'       => (int)($seasonContext['goals']       ?? 0),
        'Assists'     => (int)($seasonContext['assists']      ?? 0),
        'Yellow cards'=> (int)($seasonContext['yellow_cards'] ?? 0),
        'Red cards'   => (int)($seasonContext['red_cards']    ?? 0),
    ];
    foreach ($stats as $statLabel => $statVal):
    ?>
      <div class="stat-tile">
        <span class="label"><?= htmlspecialchars($statLabel, ENT_QUOTES, 'UTF-8') ?></span>
        <span class="val t-num"><?= htmlspecialchars((string)$statVal, ENT_QUOTES, 'UTF-8') ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- ── Match participation history ─────────────────────────── -->
<div class="bp-section">
  <h2 class="t-h3" style="margin:0 0 var(--s-4);">Match history</h2>

  <?php if (empty($matchHistory)): ?>
    <div class="bp-card bp-card--flat" style="text-align:center;padding:var(--s-5);">
      <p class="t-small muted" style="margin:0;">No match participation recorded yet.</p>
    </div>
  <?php else: ?>
    <div style="border:1px solid var(--line);border-radius:var(--r-md);overflow:hidden;">
      <table style="width:100%;border-collapse:collapse;">
        <thead>
          <tr style="border-bottom:1px solid var(--line);background:var(--bg-2);">
            <th class="t-tiny" style="padding:var(--s-3) var(--s-4);text-align:left;">Match</th>
            <th class="t-tiny" style="padding:var(--s-3) var(--s-4);text-align:left;">Date</th>
            <th class="t-tiny" style="padding:var(--s-3) var(--s-4);text-align:center;">Result</th>
            <th class="t-tiny" style="padding:var(--s-3) var(--s-4);text-align:left;">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($matchHistory as $participation): ?>
            <?php
            $mId       = (int)   ($participation['match_id']       ?? 0);
            $opponent  = (string)($participation['opponent_name']  ?? '');
            $mDate     = (string)($participation['match_date']     ?? '');
            $mStatus   = (string)($participation['match_status']   ?? '');
            $scoreOwn  = isset($participation['score_own'])      ? (int)$participation['score_own']      : null;
            $scoreOpp  = isset($participation['score_opponent']) ? (int)$participation['score_opponent'] : null;
            ?>
            <tr style="border-bottom:1px solid var(--line);">
              <td style="padding:var(--s-3) var(--s-4);">
                <a href="/matches/<?= htmlspecialchars((string)$mId, ENT_QUOTES, 'UTF-8') ?>"
                   style="font-weight:600;font-size:14px;text-decoration:none;color:inherit;">
                  vs <?= htmlspecialchars($opponent, ENT_QUOTES, 'UTF-8') ?>
                </a>
              </td>
              <td class="t-small muted" style="padding:var(--s-3) var(--s-4);">
                <?= htmlspecialchars($mDate, ENT_QUOTES, 'UTF-8') ?>
              </td>
              <td class="t-num" style="padding:var(--s-3) var(--s-4);text-align:center;font-weight:700;font-size:14px;">
                <?php if ($scoreOwn !== null && $scoreOpp !== null): ?>
                  <?= htmlspecialchars((string)$scoreOwn, ENT_QUOTES, 'UTF-8') ?>
                  &ndash;
                  <?= htmlspecialchars((string)$scoreOpp, ENT_QUOTES, 'UTF-8') ?>
                <?php else: ?>
                  –
                <?php endif; ?>
              </td>
              <td style="padding:var(--s-3) var(--s-4);">
                <span class="chip">
                  <span class="dot <?= htmlspecialchars($mStatus, ENT_QUOTES, 'UTF-8') ?>" style="margin-right:4px;"></span>
                  <?= htmlspecialchars(ucfirst($mStatus), ENT_QUOTES, 'UTF-8') ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div style="margin-top:var(--s-5);">
  <a href="/players" class="btn btn-ghost btn-sm">← All players</a>
</div>

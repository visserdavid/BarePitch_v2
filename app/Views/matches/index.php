<?php
declare(strict_types=1);

// $matches: array of match rows, each with at least: id, opponent_name, status, match_date, score_own, score_opponent
// $team:    array — the active team
$matches = isset($matches) && is_array($matches) ? $matches : [];
$team    = isset($team)    && is_array($team)    ? $team    : [];

// Group by status bucket: active → prepared → planned → finished
$grouped = ['active' => [], 'prepared' => [], 'planned' => [], 'finished' => []];
foreach ($matches as $m) {
    $s = (string)($m['status'] ?? 'planned');
    if (!array_key_exists($s, $grouped)) {
        $grouped['planned'][] = $m;
    } else {
        $grouped[$s][] = $m;
    }
}

$sections = [
    'active'   => 'Live',
    'prepared' => 'Ready to kick off',
    'planned'  => 'Upcoming',
    'finished' => 'Finished',
];
?>

<div class="bp-screen-head">
  <div>
    <p class="bp-screen-kicker"><?= htmlspecialchars((string)($team['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
    <h1 class="bp-screen-title">Matches</h1>
  </div>
  <div class="bp-cluster">
    <a href="/matches/create" class="btn btn-primary btn-sm">+ New match</a>
  </div>
</div>

<?php
$hasAny = false;
foreach ($grouped as $bucket) {
    if (!empty($bucket)) { $hasAny = true; break; }
}
?>

<?php if (!$hasAny): ?>
  <div class="bp-section">
    <div class="bp-card bp-card--flat" style="text-align:center;padding:var(--s-7);">
      <p class="t-body" style="color:var(--ink-3);margin:0;">No matches yet. <a href="/matches/create" style="color:var(--accent-line);">Create your first match.</a></p>
    </div>
  </div>

<?php else: ?>
  <?php foreach ($sections as $bucket => $label): ?>
    <?php if (empty($grouped[$bucket])) continue; ?>
    <div class="bp-section">
      <h2 class="t-tiny" style="margin:0 0 var(--s-3);letter-spacing:0.04em;">
        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
        <span style="color:var(--ink-3);margin-left:4px;">(<?= count($grouped[$bucket]) ?>)</span>
      </h2>

      <div style="border:1px solid var(--line);border-radius:var(--r-md);overflow:hidden;">
        <?php foreach ($grouped[$bucket] as $match): ?>
          <?php
          $matchId       = (int)   ($match['id']              ?? 0);
          $opponent      = (string)($match['opponent_name']   ?? '');
          $status        = (string)($match['status']          ?? 'planned');
          $scoreOwn      = isset($match['score_own'])      ? (int)$match['score_own']      : null;
          $scoreOpponent = isset($match['score_opponent']) ? (int)$match['score_opponent'] : null;
          $matchDate     = (string)($match['match_date']      ?? '');
          ?>
          <a href="/matches/<?= htmlspecialchars((string)$matchId, ENT_QUOTES, 'UTF-8') ?>"
             class="list-row"
             style="text-decoration:none;color:inherit;">
            <span>
              <span class="dot <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"></span>
            </span>
            <span>
              <span style="font-weight:600;font-size:14px;display:block;">
                vs <?= htmlspecialchars($opponent, ENT_QUOTES, 'UTF-8') ?>
              </span>
              <?php if ($matchDate !== ''): ?>
                <span class="t-small"><?= htmlspecialchars($matchDate, ENT_QUOTES, 'UTF-8') ?></span>
              <?php endif; ?>
            </span>
            <span style="text-align:right;">
              <?php if ($scoreOwn !== null && $scoreOpponent !== null): ?>
                <span class="t-num" style="font-size:16px;font-weight:700;">
                  <?= htmlspecialchars((string)$scoreOwn, ENT_QUOTES, 'UTF-8') ?>
                  &ndash;
                  <?= htmlspecialchars((string)$scoreOpponent, ENT_QUOTES, 'UTF-8') ?>
                </span>
              <?php else: ?>
                <span class="chip"><?= htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8') ?></span>
              <?php endif; ?>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php
declare(strict_types=1);

// $team:          array|null — the active team for this user
// $recentMatches: array      — recent match rows
$team          = isset($team) && is_array($team) ? $team : null;
$recentMatches = isset($recentMatches) && is_array($recentMatches) ? $recentMatches : [];
?>

<?php if ($team === null): ?>
  <!-- ── No team selected ─────────────────────────── -->
  <div class="bp-section" style="text-align:center;padding:var(--s-9) var(--s-4);">
    <p class="bp-screen-kicker">Getting started</p>
    <h1 class="bp-screen-title" style="margin-bottom:var(--s-4);">Welcome to BarePitch</h1>
    <p class="t-body" style="color:var(--ink-2);margin-bottom:var(--s-6);">
      You are not associated with a team yet. Ask your club admin to invite you, or create a team to get started.
    </p>
  </div>

<?php else: ?>
  <!-- ── Team home ─────────────────────────── -->
  <div class="bp-screen-head">
    <div>
      <p class="bp-screen-kicker">Your team</p>
      <h1 class="bp-screen-title"><?= htmlspecialchars((string)($team['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h1>
    </div>
    <div class="bp-cluster">
      <a href="/matches/create" class="btn btn-primary btn-sm">+ New match</a>
    </div>
  </div>

  <div class="bp-section">
    <h2 class="t-h3" style="margin:0 0 var(--s-4);">Recent matches</h2>

    <?php if (empty($recentMatches)): ?>
      <div class="bp-card bp-card--flat" style="text-align:center;padding:var(--s-6);">
        <p class="t-body" style="color:var(--ink-3);margin:0;">No matches yet. <a href="/matches/create" style="color:var(--accent-ink);">Create your first match.</a></p>
      </div>

    <?php else: ?>
      <div class="bp-stack" style="gap:0;border:1px solid var(--line);border-radius:var(--r-md);overflow:hidden;">
        <?php foreach ($recentMatches as $match): ?>
          <?php
          $matchId      = (int)   ($match['id']              ?? 0);
          $opponent     = (string)($match['opponent_name']   ?? '');
          $status       = (string)($match['status']          ?? 'planned');
          $scoreOwn     = isset($match['goals_scored'])   ? (int)$match['goals_scored']   : null;
          $scoreOpponent= isset($match['goals_conceded']) ? (int)$match['goals_conceded'] : null;
          $matchDate    = (string)($match['match_date']      ?? '');
          ?>
          <a href="/matches/<?= htmlspecialchars((string)$matchId, ENT_QUOTES, 'UTF-8') ?>"
             class="list-row"
             style="text-decoration:none;color:inherit;">
            <span>
              <span class="dot <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"></span>
            </span>
            <span>
              <span class="list-row__title" style="font-weight:600;font-size:14px;display:block;">
                vs <?= htmlspecialchars($opponent, ENT_QUOTES, 'UTF-8') ?>
              </span>
              <span class="list-row__meta" style="font-size:12px;color:var(--ink-3);">
                <?= htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8') ?>
                <?php if ($matchDate !== ''): ?>
                  &middot; <?= htmlspecialchars($matchDate, ENT_QUOTES, 'UTF-8') ?>
                <?php endif; ?>
              </span>
            </span>
            <span style="text-align:right;">
              <?php if ($scoreOwn !== null && $scoreOpponent !== null): ?>
                <span class="t-num" style="font-size:16px;font-weight:700;">
                  <?= htmlspecialchars((string)$scoreOwn, ENT_QUOTES, 'UTF-8') ?>
                  &ndash;
                  <?= htmlspecialchars((string)$scoreOpponent, ENT_QUOTES, 'UTF-8') ?>
                </span>
              <?php else: ?>
                <span class="t-tiny">–</span>
              <?php endif; ?>
            </span>
          </a>
        <?php endforeach; ?>
      </div>

      <div style="margin-top:var(--s-4);text-align:right;">
        <a href="/matches" class="btn btn-ghost btn-sm">All matches →</a>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

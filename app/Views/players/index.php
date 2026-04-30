<?php
declare(strict_types=1);

// $players: array — player rows (id, name, shirt_number, position)
// $team:    array — active team
$players = isset($players) && is_array($players) ? $players : [];
$team    = isset($team)    && is_array($team)    ? $team    : [];
?>

<div class="bp-screen-head">
  <div>
    <p class="bp-screen-kicker"><?= htmlspecialchars((string)($team['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
    <h1 class="bp-screen-title">Players</h1>
  </div>
  <div class="bp-cluster">
    <a href="/players/create" class="btn btn-primary btn-sm">+ Add player</a>
  </div>
</div>

<div class="bp-section">
  <?php if (empty($players)): ?>
    <div class="bp-card bp-card--flat" style="text-align:center;padding:var(--s-7);">
      <p class="t-body" style="color:var(--ink-3);margin:0;">
        No players in squad yet.
        <a href="/players/create" style="color:var(--accent-line);">Add your first player.</a>
      </p>
    </div>
  <?php else: ?>
    <div style="border:1px solid var(--line);border-radius:var(--r-md);overflow:hidden;">
      <table style="width:100%;border-collapse:collapse;">
        <thead>
          <tr style="border-bottom:1px solid var(--line);background:var(--bg-2);">
            <th class="t-tiny" style="padding:var(--s-3) var(--s-4);text-align:left;width:52px;">#</th>
            <th class="t-tiny" style="padding:var(--s-3) var(--s-4);text-align:left;">Name</th>
            <th class="t-tiny" style="padding:var(--s-3) var(--s-4);text-align:left;">Position</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($players as $player): ?>
            <?php
            $playerId  = (int)   ($player['id']           ?? 0);
            $name      = (string)($player['name']          ?? '');
            $shirtNum  = (string)($player['shirt_number']  ?? '');
            $position  = (string)($player['position']      ?? '');
            ?>
            <tr style="border-bottom:1px solid var(--line);">
              <td class="t-num" style="padding:var(--s-3) var(--s-4);font-weight:700;font-size:14px;color:var(--ink-3);">
                <?= $shirtNum !== '' ? htmlspecialchars($shirtNum, ENT_QUOTES, 'UTF-8') : '–' ?>
              </td>
              <td style="padding:var(--s-3) var(--s-4);">
                <a href="/players/<?= htmlspecialchars((string)$playerId, ENT_QUOTES, 'UTF-8') ?>"
                   style="font-weight:600;font-size:14px;text-decoration:none;color:inherit;">
                  <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>
                </a>
              </td>
              <td class="t-small muted" style="padding:var(--s-3) var(--s-4);">
                <?= $position !== '' ? htmlspecialchars(ucfirst($position), ENT_QUOTES, 'UTF-8') : '–' ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

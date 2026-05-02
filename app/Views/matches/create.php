<?php
declare(strict_types=1);

// $team:   array — active team
// $phases: array — phase records (id, name)
// $errors: array — validation error messages keyed by field name
$team   = isset($team)   && is_array($team)   ? $team   : [];
$phases = isset($phases) && is_array($phases) ? $phases : [];
$errors = isset($errors) && is_array($errors) ? $errors : [];

$old = isset($old) && is_array($old) ? $old : [];

$val = static function (string $key) use ($old): string {
    return isset($old[$key]) ? htmlspecialchars((string)$old[$key], ENT_QUOTES, 'UTF-8') : '';
};
?>

<div class="bp-screen-head">
  <div>
    <p class="bp-screen-kicker"><?= htmlspecialchars((string)($team['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
    <h1 class="bp-screen-title">New match</h1>
  </div>
</div>

<?php if (isset($errors['match'])): ?>
  <div class="bp-flash__item bp-flash__item--error" role="alert" style="margin-bottom:var(--s-4);">
    <?= htmlspecialchars((string)$errors['match'], ENT_QUOTES, 'UTF-8') ?>
  </div>
<?php endif; ?>

<div class="bp-section">
  <form method="post" action="/matches" class="bp-stack" style="max-width:560px;">
    <?php include __DIR__ . '/../partials/csrf.php'; ?>

    <!-- Opponent name -->
    <div class="field">
      <label for="opponent_name">Opponent</label>
      <input
        id="opponent_name"
        class="input<?= isset($errors['opponent_name']) ? ' input--error' : '' ?>"
        type="text"
        name="opponent_name"
        value="<?= $val('opponent_name') ?>"
        maxlength="100"
        placeholder="e.g. FC Rival"
        autocomplete="off"
        required
      >
      <?php if (isset($errors['opponent_name'])): ?>
        <span class="t-small" style="color:var(--danger);"><?= htmlspecialchars((string)$errors['opponent_name'], ENT_QUOTES, 'UTF-8') ?></span>
      <?php endif; ?>
    </div>

    <!-- Match date -->
    <div class="field">
      <label for="match_date">Match date</label>
      <input
        id="match_date"
        class="input<?= isset($errors['match_date']) ? ' input--error' : '' ?>"
        type="date"
        name="match_date"
        value="<?= $val('match_date') ?>"
        required
      >
      <?php if (isset($errors['match_date'])): ?>
        <span class="t-small" style="color:var(--danger);"><?= htmlspecialchars((string)$errors['match_date'], ENT_QUOTES, 'UTF-8') ?></span>
      <?php endif; ?>
    </div>

    <!-- Location -->
    <div class="field">
      <label for="location">Location <span class="muted">(optional)</span></label>
      <input
        id="location"
        class="input<?= isset($errors['location']) ? ' input--error' : '' ?>"
        type="text"
        name="location"
        value="<?= $val('location') ?>"
        maxlength="200"
        placeholder="e.g. Main pitch"
        autocomplete="off"
      >
      <?php if (isset($errors['location'])): ?>
        <span class="t-small" style="color:var(--danger);"><?= htmlspecialchars((string)$errors['location'], ENT_QUOTES, 'UTF-8') ?></span>
      <?php endif; ?>
    </div>

    <!-- Phase -->
    <?php if (!empty($phases)): ?>
    <div class="field">
      <label for="phase_id">Phase <span class="muted">(optional)</span></label>
      <select
        id="phase_id"
        class="input<?= isset($errors['phase_id']) ? ' input--error' : '' ?>"
        name="phase_id"
      >
        <option value="">— none —</option>
        <?php foreach ($phases as $phase): ?>
          <?php
          $phaseId   = (int)   ($phase['id']   ?? 0);
          $phaseName = (string)($phase['name'] ?? '');
          $selected  = ($val('phase_id') === (string)$phaseId) ? 'selected' : '';
          ?>
          <option value="<?= htmlspecialchars((string)$phaseId, ENT_QUOTES, 'UTF-8') ?>" <?= $selected ?>>
            <?= htmlspecialchars($phaseName, ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($errors['phase_id'])): ?>
        <span class="t-small" style="color:var(--danger);"><?= htmlspecialchars((string)$errors['phase_id'], ENT_QUOTES, 'UTF-8') ?></span>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="bp-cluster" style="justify-content:flex-end;gap:var(--s-3);margin-top:var(--s-2);">
      <a href="/matches" class="btn btn-secondary">Cancel</a>
      <button type="submit" class="btn btn-primary">Create match</button>
    </div>
  </form>
</div>

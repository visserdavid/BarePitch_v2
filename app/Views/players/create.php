<?php
declare(strict_types=1);

// $team:   array — active team
// $errors: array — validation errors keyed by field name
$team   = isset($team)   && is_array($team)   ? $team   : [];
$errors = isset($errors) && is_array($errors) ? $errors : [];
$old    = isset($old)    && is_array($old)    ? $old    : [];

$val = static function (string $key) use ($old): string {
    return isset($old[$key]) ? htmlspecialchars((string)$old[$key], ENT_QUOTES, 'UTF-8') : '';
};

$positions = ['goalkeeper', 'defender', 'midfielder', 'forward'];
?>

<div class="bp-screen-head">
  <div>
    <p class="bp-screen-kicker"><?= htmlspecialchars((string)($team['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
    <h1 class="bp-screen-title">Add player</h1>
  </div>
</div>

<div class="bp-section">
  <form method="post" action="/players" class="bp-stack" style="max-width:480px;">
    <?php include __DIR__ . '/../partials/csrf.php'; ?>

    <!-- Name -->
    <div class="field">
      <label for="name">Full name</label>
      <input
        id="name"
        class="input<?= isset($errors['name']) ? ' input--error' : '' ?>"
        type="text"
        name="name"
        value="<?= $val('name') ?>"
        maxlength="100"
        placeholder="e.g. Jan de Vries"
        autocomplete="off"
        required
      >
      <?php if (isset($errors['name'])): ?>
        <span class="t-small" style="color:var(--danger);"><?= htmlspecialchars((string)$errors['name'], ENT_QUOTES, 'UTF-8') ?></span>
      <?php endif; ?>
    </div>

    <!-- Shirt number -->
    <div class="field">
      <label for="shirt_number">Shirt number <span class="muted">(optional)</span></label>
      <input
        id="shirt_number"
        class="input<?= isset($errors['shirt_number']) ? ' input--error' : '' ?>"
        type="number"
        name="shirt_number"
        value="<?= $val('shirt_number') ?>"
        min="1"
        max="99"
        placeholder="e.g. 7"
      >
      <?php if (isset($errors['shirt_number'])): ?>
        <span class="t-small" style="color:var(--danger);"><?= htmlspecialchars((string)$errors['shirt_number'], ENT_QUOTES, 'UTF-8') ?></span>
      <?php endif; ?>
    </div>

    <!-- Position -->
    <div class="field">
      <label for="position">Position <span class="muted">(optional)</span></label>
      <select
        id="position"
        class="input<?= isset($errors['position']) ? ' input--error' : '' ?>"
        name="position"
      >
        <option value="">— choose —</option>
        <?php foreach ($positions as $pos): ?>
          <option
            value="<?= htmlspecialchars($pos, ENT_QUOTES, 'UTF-8') ?>"
            <?= ($val('position') === $pos) ? 'selected' : '' ?>
          >
            <?= htmlspecialchars(ucfirst($pos), ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($errors['position'])): ?>
        <span class="t-small" style="color:var(--danger);"><?= htmlspecialchars((string)$errors['position'], ENT_QUOTES, 'UTF-8') ?></span>
      <?php endif; ?>
    </div>

    <div class="bp-cluster" style="justify-content:flex-end;gap:var(--s-3);margin-top:var(--s-2);">
      <a href="/players" class="btn btn-secondary">Cancel</a>
      <button type="submit" class="btn btn-primary">Add player</button>
    </div>
  </form>
</div>

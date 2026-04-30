<?php
declare(strict_types=1);

// $users: array of user rows; provided via extract($data).
$users = isset($users) && is_array($users) ? $users : [];
?>
<div class="login-wrap" style="max-width:480px; margin:0 auto;">
  <div class="brand-stack">
    <span class="bp-brand__mark" aria-hidden="true" style="width:40px;height:40px;border-radius:10px;font-size:20px;display:inline-grid;place-items:center;background:var(--ink);color:var(--ink-inv);font-weight:750;position:relative;flex:0 0 auto;">B</span>
    <span class="bp-brand__name" style="font-size:20px;font-weight:650;">BarePitch</span>
  </div>

  <h2>Dev Login</h2>
  <p class="lede">Select a user to log in as. This screen is only available in development mode.</p>

  <?php if (empty($users)): ?>
    <p class="t-small" style="color:var(--danger);">No users found. Seed the database first.</p>
  <?php else: ?>
    <form method="post" action="/dev-login" class="form">
      <?php include __DIR__ . '/../partials/csrf.php'; ?>

      <fieldset style="border:0;padding:0;margin:0;display:flex;flex-direction:column;gap:var(--s-2);">
        <legend class="t-tiny" style="margin-bottom:var(--s-3);">Choose a user</legend>

        <?php foreach ($users as $i => $user): ?>
          <?php
          $userId   = (int)   ($user['id']    ?? 0);
          $userName = (string)($user['name']  ?? '');
          $userEmail= (string)($user['email'] ?? '');
          ?>
          <label class="panel" style="display:flex;align-items:center;gap:var(--s-3);cursor:pointer;padding:var(--s-3) var(--s-4);">
            <input
              type="radio"
              name="user_id"
              value="<?= htmlspecialchars((string)$userId, ENT_QUOTES, 'UTF-8') ?>"
              <?= $i === 0 ? 'checked' : '' ?>
              style="accent-color:var(--accent);"
            >
            <span>
              <span class="t-body" style="font-weight:600;display:block;"><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></span>
              <span class="t-small"><?= htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8') ?></span>
            </span>
          </label>
        <?php endforeach; ?>
      </fieldset>

      <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:var(--s-3);">
        Log in as selected user
      </button>
    </form>
  <?php endif; ?>
</div>

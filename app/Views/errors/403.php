<?php
declare(strict_types=1);

$message = isset($message) && is_string($message) ? $message : 'You do not have permission to access this page.';
?>
<div class="bp-section" style="text-align:center; padding: var(--s-9) var(--s-4);">
  <p class="bp-screen-kicker">403</p>
  <h1 class="bp-screen-title" style="margin-bottom:var(--s-4);">Access denied</h1>
  <p class="t-body" style="color:var(--ink-2); margin-bottom:var(--s-6);">
    <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
  </p>
  <a href="/" class="btn btn-secondary">Back to Home</a>
</div>

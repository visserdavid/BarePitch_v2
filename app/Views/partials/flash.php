<?php
declare(strict_types=1);

// Expects $flash to be an array with optional keys 'success' and/or 'error'
// Provided via extract($data) from the layout.
$flash = isset($flash) && is_array($flash) ? $flash : [];

$success = isset($flash['success']) && is_string($flash['success']) ? $flash['success'] : '';
$error   = isset($flash['error'])   && is_string($flash['error'])   ? $flash['error']   : '';

if ($success === '' && $error === '') {
    return;
}
?>
<div class="bp-flash" role="alert" aria-live="polite">
<?php if ($success !== ''): ?>
  <div class="bp-flash__item bp-flash__item--success">
    <svg class="bp-icon" aria-hidden="true"><use href="/assets/barepitch-icons.svg#bp-icon-check-circle"></use></svg>
    <span><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></span>
  </div>
<?php endif; ?>
<?php if ($error !== ''): ?>
  <div class="bp-flash__item bp-flash__item--error">
    <svg class="bp-icon" aria-hidden="true"><use href="/assets/barepitch-icons.svg#bp-icon-alert-circle"></use></svg>
    <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
  </div>
<?php endif; ?>
</div>

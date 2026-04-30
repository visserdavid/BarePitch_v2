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
<style>
.bp-flash { display: flex; flex-direction: column; gap: var(--s-2); margin-bottom: var(--s-4); }
.bp-flash__item {
  display: flex;
  align-items: center;
  gap: var(--s-3);
  padding: var(--s-3) var(--s-4);
  border-radius: var(--r-md);
  font-size: 14px;
  font-weight: 500;
}
.bp-flash__item--success { background: var(--accent-soft); color: var(--accent-ink); }
.bp-flash__item--error   { background: color-mix(in oklch, var(--danger) 15%, var(--bg)); color: var(--danger); border: 1px solid color-mix(in oklch, var(--danger) 30%, transparent); }
</style>

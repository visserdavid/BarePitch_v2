<?php
declare(strict_types=1);

$name = isset($name) && is_string($name) ? $name : 'ball';
$class = isset($class) && is_string($class) ? $class : 'bp-icon';
$label = isset($label) && is_string($label) ? $label : '';
$safeName = preg_replace('/[^a-z0-9-]/', '', strtolower($name)) ?: 'ball';
?>
<svg class="<?= htmlspecialchars($class, ENT_QUOTES, 'UTF-8') ?>" <?= $label === '' ? 'aria-hidden="true"' : 'role="img" aria-label="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '"' ?>>
  <use href="/assets/barepitch-icons.svg#bp-icon-<?= htmlspecialchars($safeName, ENT_QUOTES, 'UTF-8') ?>"></use>
</svg>

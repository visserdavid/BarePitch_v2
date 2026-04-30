<?php
declare(strict_types=1);

// $currentPath is expected to be provided via extract($data).
// Falls back to REQUEST_URI so the partial works without it.
$currentPath = isset($currentPath) && is_string($currentPath)
    ? $currentPath
    : (isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '/');

$items = [
    ['href' => '/',         'icon' => 'home',    'label' => 'Home'],
    ['href' => '/matches',  'icon' => 'ball',    'label' => 'Matches'],
    ['href' => '/players',  'icon' => 'users',   'label' => 'Players'],
    ['href' => '/settings', 'icon' => 'settings','label' => 'Settings'],
];

/**
 * Determine if a nav item is active.
 * The home item only matches '/'; other items match prefix.
 */
$isActive = static function (string $href, string $path): bool {
    if ($href === '/') {
        return $path === '/';
    }
    return str_starts_with($path, $href);
};
?>
<nav class="bp-bottom-nav" aria-label="Main navigation">
<?php foreach ($items as $item): ?>
  <?php $active = $isActive($item['href'], $currentPath); ?>
  <a href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>"
     <?= $active ? 'aria-current="page"' : '' ?>>
    <svg class="bp-icon" aria-hidden="true">
      <use href="/assets/barepitch-icons.svg#bp-icon-<?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"></use>
    </svg>
    <span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
  </a>
<?php endforeach; ?>
</nav>

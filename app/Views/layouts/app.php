<?php
declare(strict_types=1);

$title      = isset($title)      && is_string($title)      ? $title      : 'BarePitch';
$theme      = isset($theme)      && $theme === 'dark'       ? 'dark'      : 'light';
$bodyClass  = isset($bodyClass)  && is_string($bodyClass)   ? $bodyClass  : '';
$content    = isset($content)    && is_string($content)     ? $content    : '';
// When $hideNav is truthy the bottom navigation is suppressed (e.g. dev-login).
$hideNav    = !empty($hideNav);
// $flash and $currentPath are passed through for partials.
$flash       = isset($flash)       && is_array($flash)       ? $flash       : [];
$currentPath = isset($currentPath) && is_string($currentPath) ? $currentPath : '/';
?>
<!doctype html>
<html lang="en" data-theme="<?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light dark">
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600;700&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/css/brand.css">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body class="bp-app <?= htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8') ?>">
  <header class="bp-app-header">
    <div class="bp-app-header__inner">
      <a class="bp-brand" href="/" aria-label="BarePitch home">
        <span class="bp-brand__mark" aria-hidden="true">B</span>
        <span class="bp-brand__name">BarePitch</span>
      </a>
    </div>
  </header>

  <main class="bp-app-shell">
    <?php include __DIR__ . '/../partials/flash.php'; ?>
    <?= $content ?>
  </main>

  <?php if (!$hideNav): ?>
    <?php include __DIR__ . '/../partials/bottom-nav.php'; ?>
  <?php endif; ?>
</body>
</html>

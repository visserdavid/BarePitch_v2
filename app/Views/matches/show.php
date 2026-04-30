<?php
declare(strict_types=1);

// $match: array with at least 'status'
// $team:  array
$match = isset($match) && is_array($match) ? $match : [];
$team  = isset($team)  && is_array($team)  ? $team  : [];

$status = (string)($match['status'] ?? 'planned');

// Delegate to the appropriate sub-view based on status.
// planned/prepared  → prepare.php
// active            → live.php
// finished          → summary.php
$subView = match ($status) {
    'active'   => __DIR__ . '/live.php',
    'finished' => __DIR__ . '/summary.php',
    default    => __DIR__ . '/prepare.php',
};

if (file_exists($subView)) {
    include $subView;
} else {
    echo '<p class="t-body" style="color:var(--danger);">View not found for status: '
        . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</p>';
}

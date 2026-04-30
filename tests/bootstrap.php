<?php

declare(strict_types=1);

// Load the Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load .env.testing if present (test-specific environment overrides)
$envTestingFile = __DIR__ . '/../.env.testing';
if (file_exists($envTestingFile)) {
    $lines = file($envTestingFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        // Skip comments
        if (str_starts_with($line, '#') || $line === '') {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            // Only set if not already set via phpunit.xml <env> or shell env
            if (getenv($key) === false) {
                putenv("{$key}={$value}");
                $_ENV[$key]    = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

// Initialise a bare $_SESSION for tests that exercise session-based code.
// Session must not be started by PHPUnit itself — we just set the superglobal.
if (!isset($_SESSION)) {
    $_SESSION = [];
}

<?php
declare(strict_types=1);

// Load .env (same inline loader as migrate.php)
$envPath = dirname(__DIR__) . '/.env';
if (file_exists($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        putenv(trim($key) . '=' . trim($value));
    }
}

$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=utf8mb4',
    getenv('DB_HOST') ?: '127.0.0.1',
    getenv('DB_NAME') ?: 'barepitch'
);

try {
    $pdo = new PDO($dsn, getenv('DB_USER') ?: 'root', getenv('DB_PASSWORD') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    echo "[ERROR] Cannot connect to database: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

$seedFile = dirname(__DIR__) . '/database/seeds/seed_v010.sql';
if (!file_exists($seedFile)) {
    echo "[ERROR] Seed file not found: $seedFile" . PHP_EOL;
    exit(1);
}

echo "Running seed: seed_v010.sql ... ";
$sql = file_get_contents($seedFile);
try {
    $pdo->exec($sql);
    echo "OK" . PHP_EOL;
} catch (PDOException $e) {
    echo "FAILED" . PHP_EOL;
    echo "[ERROR] " . $e->getMessage() . PHP_EOL;
    exit(1);
}

echo "Seed complete." . PHP_EOL;

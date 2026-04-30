<?php
declare(strict_types=1);

// Load .env
$envPath = dirname(__DIR__) . '/.env';
if (file_exists($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $value = trim($value);
        if (strlen($value) >= 2) {
            $firstChar = $value[0];
            $lastChar  = $value[-1];
            if (($firstChar === '"' && $lastChar === '"') ||
                ($firstChar === "'" && $lastChar === "'")) {
                $value = substr($value, 1, -1);
            }
        }
        putenv(trim($key) . '=' . $value);
    }
}

// Connect
$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=utf8mb4',
    getenv('DB_HOST') ?: '127.0.0.1',
    getenv('DB_NAME') ?: 'barepitch'
);

try {
    $pdo = new PDO($dsn, getenv('DB_USER') ?: 'root', getenv('DB_PASSWORD') ?: '', [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    echo "[ERROR] Cannot connect to database: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// Create migrations table if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS migrations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    filename VARCHAR(255) NOT NULL,
    executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_migrations_filename (filename)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Load already-executed migrations
$executed = $pdo->query("SELECT filename FROM migrations")->fetchAll(PDO::FETCH_COLUMN);
$executed = array_flip($executed);

// Find migration files
$migrationsDir = dirname(__DIR__) . '/database/migrations';
$files = glob($migrationsDir . '/*.sql');
sort($files);

if (empty($files)) {
    echo "No migration files found." . PHP_EOL;
    exit(0);
}

$ran = 0;
foreach ($files as $filePath) {
    $filename = basename($filePath);
    if (isset($executed[$filename])) {
        echo "[SKIP] $filename" . PHP_EOL;
        continue;
    }

    echo "[RUN]  $filename ... ";
    $sql = file_get_contents($filePath);

    try {
        $pdo->exec($sql);
        $stmt = $pdo->prepare("INSERT INTO migrations (filename) VALUES (?)");
        $stmt->execute([$filename]);
        echo "OK" . PHP_EOL;
        $ran++;
    } catch (PDOException $e) {
        echo "FAILED" . PHP_EOL;
        echo "[ERROR] " . $e->getMessage() . PHP_EOL;
        exit(1);
    }
}

echo PHP_EOL . "Done. $ran migration(s) executed." . PHP_EOL;

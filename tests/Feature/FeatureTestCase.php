<?php

declare(strict_types=1);

namespace BarePitch\Tests\Feature;

use BarePitch\Core\Database;
use PHPUnit\Framework\TestCase;

/**
 * Base class for feature tests that require a real database.
 *
 * Each concrete test class gets a clean database state via truncation
 * in setUp(). Core fixture data (club, season, team, user, roles) is
 * re-seeded before every test.
 *
 * DB connection is established once per test class via setUpBeforeClass().
 * If the DB is not available the tests are skipped cleanly.
 */
abstract class FeatureTestCase extends TestCase
{
    protected static \PDO $db;

    /** Fixture IDs populated by seedCoreData() */
    protected static int $clubId    = 0;
    protected static int $seasonId  = 0;
    protected static int $teamId    = 0;
    protected static int $phaseId   = 0;
    protected static int $coachId   = 0;
    protected static int $trainerId = 0;

    // ----------------------------------------------------------------
    // Connection + schema bootstrap
    // ----------------------------------------------------------------

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $host = getenv('DB_HOST')     ?: '127.0.0.1';
        $name = getenv('DB_NAME')     ?: 'barepitch_test';
        $user = getenv('DB_USER')     ?: 'root';
        $pass = getenv('DB_PASSWORD') ?: '';

        try {
            $pdo = new \PDO(
                "mysql:host={$host};dbname={$name};charset=utf8mb4",
                $user,
                $pass,
                [
                    \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
            static::$db = $pdo;

            // Make Database singleton aware of the test connection
            self::injectDatabaseConnection($pdo);

            // Run migrations if tables are missing
            static::ensureSchema($pdo);
        } catch (\PDOException $e) {
            static::markTestSkipped(
                'Test database unavailable — configure .env.testing. Error: ' . $e->getMessage()
            );
        }
    }

    // ----------------------------------------------------------------
    // Per-test setup: truncate and re-seed
    // ----------------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();

        if (!isset(static::$db)) {
            $this->markTestSkipped('No test DB connection available.');
            return;
        }

        $this->truncateMatchTables();
        $this->truncateCoreFixtureTables();
        $this->seedCoreData();

        // Reset session state
        $_SESSION = [];
    }

    // ----------------------------------------------------------------
    // Authentication helper
    // ----------------------------------------------------------------

    /**
     * Simulates authentication by setting the session user_id.
     * The $userId must match a user inserted by seedCoreData().
     */
    protected function actingAs(int $userId): void
    {
        $_SESSION['user_id'] = $userId;
    }

    // ----------------------------------------------------------------
    // DB helpers
    // ----------------------------------------------------------------

    protected function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = static::$db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    protected function fetchAll(string $sql, array $params = []): array
    {
        $stmt = static::$db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    protected function execute(string $sql, array $params = []): void
    {
        $stmt = static::$db->prepare($sql);
        $stmt->execute($params);
    }

    protected function lastInsertId(): int
    {
        return (int) static::$db->lastInsertId();
    }

    // ----------------------------------------------------------------
    // Match fixture builder helpers
    // ----------------------------------------------------------------

    /**
     * Inserts a minimal match row and returns its ID.
     * Defaults to status=planned, team=static::$teamId.
     */
    protected function createMatch(array $overrides = []): int
    {
        $defaults = [
            'team_id'                          => static::$teamId,
            'phase_id'                         => static::$phaseId,
            'date'                             => '2026-05-01',
            'opponent_name'                    => 'Test FC',
            'home_away'                        => 'home',
            'match_type'                       => 'league',
            'regular_half_duration_minutes'    => 45,
            'extra_time_half_duration_minutes' => 15,
            'status'                           => 'planned',
            'active_phase'                     => 'none',
            'goals_scored'                     => 0,
            'goals_conceded'                   => 0,
            'shootout_goals_scored'            => 0,
            'shootout_goals_conceded'          => 0,
            'created_by'                       => static::$coachId,
        ];

        $data = array_merge($defaults, $overrides);

        $cols = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $this->execute(
            "INSERT INTO `match` ({$cols}) VALUES ({$placeholders})",
            array_values($data)
        );

        return $this->lastInsertId();
    }

    /**
     * Inserts a player row and returns the player ID.
     */
    protected function createPlayer(string $firstName = 'John', string $lastName = 'Doe'): int
    {
        $this->execute(
            "INSERT INTO player (first_name, last_name) VALUES (?, ?)",
            [$firstName, $lastName]
        );
        return $this->lastInsertId();
    }

    /**
     * Inserts a match_selection row (attendance_status='present' by default) and returns the selection ID.
     */
    protected function createSelection(int $matchId, int $playerId, array $overrides = []): int
    {
        $defaults = [
            'match_id'         => $matchId,
            'player_id'        => $playerId,
            'attendance_status' => 'present',
            'is_starting'      => 0,
            'is_on_bench'      => 0,
            'is_active_on_field' => 0,
            'is_sent_off'      => 0,
            'can_reenter'      => 1,
            'playing_time_seconds' => 0,
            'guest_type'       => 'none',
            'is_guest'         => 0,
        ];

        $data = array_merge($defaults, $overrides);

        $cols = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $this->execute(
            "INSERT INTO match_selection ({$cols}) VALUES ({$placeholders})",
            array_values($data)
        );

        return $this->lastInsertId();
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    private function truncateMatchTables(): void
    {
        $tables = [
            'audit_log',
            'match_event',
            'match_lock',
            'match_lineup_slot',
            'match_selection',
            'match_period',
            '`match`',
        ];

        static::$db->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $table) {
            static::$db->exec("TRUNCATE TABLE {$table}");
        }
        static::$db->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function truncateCoreFixtureTables(): void
    {
        $tables = [
            'formation_position',
            'formation',
            'player_season_context',
            'user_team_role',
            'player',
            'team',
            'phase',
            'season',
            'user',
            'club',
        ];

        static::$db->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $table) {
            static::$db->exec("TRUNCATE TABLE {$table}");
        }
        static::$db->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * Seeds the minimum fixture data needed by all feature tests.
     * Populates static::$clubId, $seasonId, $teamId, $phaseId, $coachId, $trainerId.
     */
    private function seedCoreData(): void
    {
        // Club
        $this->execute("INSERT INTO club (name) VALUES ('Test Club')");
        static::$clubId = $this->lastInsertId();

        // Season
        $this->execute(
            "INSERT INTO season (club_id, label, starts_on, ends_on) VALUES (?, '2025-2026', '2025-08-01', '2026-06-30')",
            [static::$clubId]
        );
        static::$seasonId = $this->lastInsertId();

        // Team
        $this->execute(
            "INSERT INTO team (club_id, season_id, name) VALUES (?, ?, 'Test Team')",
            [static::$clubId, static::$seasonId]
        );
        static::$teamId = $this->lastInsertId();

        // Phase
        $this->execute(
            "INSERT INTO phase (season_id, number, label) VALUES (?, 1, 'Phase 1')",
            [static::$seasonId]
        );
        static::$phaseId = $this->lastInsertId();

        // Coach user
        $this->execute(
            "INSERT INTO user (first_name, last_name, email) VALUES ('Coach', 'User', 'coach@test.com')"
        );
        static::$coachId = $this->lastInsertId();

        $this->execute(
            "INSERT INTO user_team_role (user_id, team_id, role_key) VALUES (?, ?, 'coach')",
            [static::$coachId, static::$teamId]
        );

        // Trainer user (limited permissions)
        $this->execute(
            "INSERT INTO user (first_name, last_name, email) VALUES ('Trainer', 'User', 'trainer@test.com')"
        );
        static::$trainerId = $this->lastInsertId();

        $this->execute(
            "INSERT INTO user_team_role (user_id, team_id, role_key) VALUES (?, ?, 'trainer')",
            [static::$trainerId, static::$teamId]
        );
    }

    /**
     * Returns a user array in the format used by the application (with roles attached).
     */
    protected function loadUser(int $userId): array
    {
        $user = $this->fetchOne("SELECT * FROM user WHERE id = ?", [$userId]);
        if ($user === null) {
            throw new \RuntimeException("User {$userId} not found in test DB.");
        }

        $roles = $this->fetchAll(
            "SELECT team_id, role_key FROM user_team_role WHERE user_id = ?",
            [$userId]
        );
        $user['roles'] = $roles;

        return $user;
    }

    /**
     * Ensures all migration SQL files have been run against the test DB.
     */
    private static function ensureSchema(\PDO $pdo): void
    {
        // Check if the migrations table exists as a proxy for schema presence
        $result = $pdo->query(
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = 'migrations'"
        );
        $exists = (bool) $result->fetchColumn();

        if ($exists) {
            return;
        }

        $migrationsDir = __DIR__ . '/../../database/migrations';
        $files = glob($migrationsDir . '/*.sql');
        if (!$files) {
            return;
        }
        sort($files);

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($files as $file) {
            $sql = file_get_contents($file);
            if ($sql !== false) {
                $pdo->exec($sql);
            }
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * Injects the test PDO instance into the Database singleton so that
     * service/repository code that calls Database::connection() uses the
     * test database.
     */
    private static function injectDatabaseConnection(\PDO $pdo): void
    {
        $ref = new \ReflectionProperty(Database::class, 'connection');
        $ref->setValue(null, $pdo);
    }
}

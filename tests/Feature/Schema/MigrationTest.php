<?php

declare(strict_types=1);

namespace BarePitch\Tests\Feature\Schema;

use BarePitch\Tests\Feature\FeatureTestCase;

/**
 * Verifies that all expected tables exist in the test database after
 * migrations have been applied.
 *
 * Also spot-checks key foreign-key constraints and column presence.
 */
class MigrationTest extends FeatureTestCase
{
    // ----------------------------------------------------------------
    // Table existence
    // ----------------------------------------------------------------

    /**
     * @dataProvider expectedTablesProvider
     */
    public function testExpectedTableExists(string $tableName): void
    {
        $row = $this->fetchOne(
            "SELECT COUNT(*) AS cnt FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = ?",
            [$tableName]
        );

        $this->assertSame(
            1,
            (int) $row['cnt'],
            "Expected table '{$tableName}' to exist in the test database."
        );
    }

    public static function expectedTablesProvider(): array
    {
        return [
            ['club'],
            ['season'],
            ['phase'],
            ['team'],
            ['user'],
            ['user_team_role'],
            ['player'],
            ['player_season_context'],
            ['formation'],
            ['formation_position'],
            ['match'],
            ['match_period'],
            ['match_selection'],
            ['match_lineup_slot'],
            ['match_event'],
            ['match_lock'],
            ['audit_log'],
            ['migrations'],
        ];
    }

    // ----------------------------------------------------------------
    // Key column checks
    // ----------------------------------------------------------------

    public function testMatchTableHasStatusColumn(): void
    {
        $this->assertColumnExists('match', 'status');
    }

    public function testMatchTableHasFormationIdColumn(): void
    {
        // Added in migration 2 (add_formation_id_to_match)
        $this->assertColumnExists('match', 'formation_id');
    }

    public function testMatchSelectionHasAttendanceStatusColumn(): void
    {
        $this->assertColumnExists('match_selection', 'attendance_status');
    }

    public function testMatchEventHasOutcomeColumn(): void
    {
        $this->assertColumnExists('match_event', 'outcome');
    }

    public function testMatchEventHasTeamSideColumn(): void
    {
        $this->assertColumnExists('match_event', 'team_side');
    }

    public function testUserTeamRoleHasRoleKeyColumn(): void
    {
        $this->assertColumnExists('user_team_role', 'role_key');
    }

    // ----------------------------------------------------------------
    // Foreign key constraint checks (spot-check via INFORMATION_SCHEMA)
    // ----------------------------------------------------------------

    public function testMatchTableHasForeignKeyToTeam(): void
    {
        $this->assertForeignKeyExists('match', 'team_id', 'team');
    }

    public function testMatchSelectionHasForeignKeyToMatch(): void
    {
        $this->assertForeignKeyExists('match_selection', 'match_id', 'match');
    }

    public function testMatchEventHasForeignKeyToMatch(): void
    {
        $this->assertForeignKeyExists('match_event', 'match_id', 'match');
    }

    // ----------------------------------------------------------------
    // Private assertion helpers
    // ----------------------------------------------------------------

    private function assertColumnExists(string $table, string $column): void
    {
        $row = $this->fetchOne(
            "SELECT COUNT(*) AS cnt FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?",
            [$table, $column]
        );

        $this->assertSame(
            1,
            (int) $row['cnt'],
            "Expected column '{$column}' to exist in table '{$table}'."
        );
    }

    private function assertForeignKeyExists(string $fromTable, string $fromColumn, string $toTable): void
    {
        $row = $this->fetchOne(
            "SELECT COUNT(*) AS cnt
             FROM information_schema.key_column_usage kcu
             JOIN information_schema.referential_constraints rc
               ON rc.constraint_name = kcu.constraint_name
              AND rc.constraint_schema = kcu.constraint_schema
             WHERE kcu.table_schema   = DATABASE()
               AND kcu.table_name     = ?
               AND kcu.column_name    = ?
               AND rc.referenced_table_name = ?",
            [$fromTable, $fromColumn, $toTable]
        );

        $this->assertGreaterThanOrEqual(
            1,
            (int) $row['cnt'],
            "Expected a foreign key from {$fromTable}.{$fromColumn} to {$toTable}."
        );
    }
}

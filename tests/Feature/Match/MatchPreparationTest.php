<?php

declare(strict_types=1);

namespace BarePitch\Tests\Feature\Match;

use BarePitch\Core\Exceptions\ValidationException;
use BarePitch\Repositories\AuditRepository;
use BarePitch\Repositories\LineupRepository;
use BarePitch\Repositories\MatchRepository;
use BarePitch\Repositories\SelectionRepository;
use BarePitch\Repositories\TeamRepository;
use BarePitch\Services\AuditService;
use BarePitch\Services\MatchPreparationService;
use BarePitch\Tests\Feature\FeatureTestCase;

/**
 * Tests MatchPreparationService::confirmPreparation() against a real DB.
 *
 * Covers:
 *   - Confirm fails with 10 players (< 11 present)
 *   - Confirm fails with incomplete lineup (missing formation position)
 *   - Confirm succeeds → match status becomes 'prepared'
 */
class MatchPreparationTest extends FeatureTestCase
{
    private MatchPreparationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->buildService();
    }

    // ----------------------------------------------------------------
    // Fails: fewer than 11 present players
    // ----------------------------------------------------------------

    public function testConfirmFailsWithTenPresentPlayers(): void
    {
        $matchId = $this->createMatch();
        $match   = $this->fetchOne('SELECT * FROM `match` WHERE id = ?', [$matchId]);
        $user    = $this->loadUser(static::$coachId);

        // Create a formation with 11 positions
        $formationId = $this->createFormation(static::$teamId);
        $positionIds = $this->createFormationPositions($formationId, 11);

        // Add only 10 present players with complete lineup
        $playerIds = [];
        for ($i = 0; $i < 10; $i++) {
            $playerIds[] = $this->createPlayer("Player{$i}", 'A');
        }

        $slots = [];
        foreach ($playerIds as $idx => $playerId) {
            $selId  = $this->createSelection($matchId, $playerId);
            $slots[] = [
                'match_selection_id'    => $selId,
                'formation_position_id' => $positionIds[$idx],
                'grid_row'              => $idx + 1,
                'grid_col'              => 1,
            ];
        }

        $lineupRepo = new LineupRepository(static::$db);
        $lineupRepo->replaceForMatch($matchId, $slots);

        $this->expectException(ValidationException::class);
        $this->service->confirmPreparation($user, $match);
    }

    // ----------------------------------------------------------------
    // Fails: incomplete lineup (not all positions filled)
    // ----------------------------------------------------------------

    public function testConfirmFailsWithIncompleteLineup(): void
    {
        $matchId = $this->createMatch();
        $match   = $this->fetchOne('SELECT * FROM `match` WHERE id = ?', [$matchId]);
        $user    = $this->loadUser(static::$coachId);

        // Create a formation with 11 positions
        $formationId = $this->createFormation(static::$teamId);
        $positionIds = $this->createFormationPositions($formationId, 11);

        // Add 11 present players but only fill 10 lineup positions
        $playerIds = [];
        for ($i = 0; $i < 11; $i++) {
            $playerIds[] = $this->createPlayer("Player{$i}", 'B');
        }

        $slots = [];
        foreach (array_slice($playerIds, 0, 10) as $idx => $playerId) {
            $selId   = $this->createSelection($matchId, $playerId);
            $slots[] = [
                'match_selection_id'    => $selId,
                'formation_position_id' => $positionIds[$idx],
                'grid_row'              => $idx + 1,
                'grid_col'              => 1,
            ];
        }

        // 11th player present but NOT in lineup (no slot)
        $this->createSelection($matchId, $playerIds[10]);

        $lineupRepo = new LineupRepository(static::$db);
        $lineupRepo->replaceForMatch($matchId, $slots);

        $this->expectException(ValidationException::class);
        $this->service->confirmPreparation($user, $match);
    }

    // ----------------------------------------------------------------
    // Succeeds: valid preparation → status = prepared
    // ----------------------------------------------------------------

    public function testConfirmSucceedsWithValidPreparation(): void
    {
        $matchId = $this->createMatch();
        $match   = $this->fetchOne('SELECT * FROM `match` WHERE id = ?', [$matchId]);
        $user    = $this->loadUser(static::$coachId);

        // Create a formation with 11 positions
        $formationId = $this->createFormation(static::$teamId);
        $positionIds = $this->createFormationPositions($formationId, 11);

        // Add 11 present players with a complete lineup
        $slots = [];
        for ($i = 0; $i < 11; $i++) {
            $playerId = $this->createPlayer("Player{$i}", 'C');
            $selId    = $this->createSelection($matchId, $playerId);
            $slots[]  = [
                'match_selection_id'    => $selId,
                'formation_position_id' => $positionIds[$i],
                'grid_row'              => $i + 1,
                'grid_col'              => 1,
            ];
        }

        $lineupRepo = new LineupRepository(static::$db);
        $lineupRepo->replaceForMatch($matchId, $slots);

        // Must not throw
        $this->service->confirmPreparation($user, $match);

        $updated = $this->fetchOne('SELECT status FROM `match` WHERE id = ?', [$matchId]);
        $this->assertSame('prepared', $updated['status']);

        // All 11 slotted selections must be marked as starters
        $selections = $this->fetchAll(
            'SELECT is_starting, is_on_bench FROM match_selection WHERE match_id = ?',
            [$matchId]
        );
        $this->assertCount(11, $selections);

        $slottedIds = array_column($slots, 'match_selection_id');
        $slottedSelections = $this->fetchAll(
            'SELECT id, is_starting FROM match_selection WHERE match_id = ? AND id IN (' .
            implode(',', array_fill(0, count($slottedIds), '?')) . ')',
            array_merge([$matchId], $slottedIds)
        );
        foreach ($slottedSelections as $sel) {
            $this->assertSame(1, (int) $sel['is_starting'], "Selection {$sel['id']} should have is_starting = 1");
        }

        // Any present-but-unslotted players should have is_on_bench = 1
        $unslottedSelections = $this->fetchAll(
            'SELECT id, is_on_bench FROM match_selection WHERE match_id = ? AND id NOT IN (' .
            implode(',', array_fill(0, count($slottedIds), '?')) . ')',
            array_merge([$matchId], $slottedIds)
        );
        foreach ($unslottedSelections as $sel) {
            $this->assertSame(1, (int) $sel['is_on_bench'], "Selection {$sel['id']} should have is_on_bench = 1");
        }
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    private function buildService(): MatchPreparationService
    {
        $pdo          = static::$db;
        $matchRepo    = new MatchRepository($pdo);
        $selectionRepo = new SelectionRepository($pdo);
        $lineupRepo   = new LineupRepository($pdo);
        $teamRepo     = new TeamRepository($pdo);
        $auditRepo    = new AuditRepository($pdo);
        $auditService = new AuditService($auditRepo);

        return new MatchPreparationService($matchRepo, $selectionRepo, $lineupRepo, $teamRepo, $auditService);
    }

    private function createFormation(int $teamId): int
    {
        $this->execute(
            "INSERT INTO formation (team_id, name) VALUES (?, '4-4-2')",
            [$teamId]
        );
        return $this->lastInsertId();
    }

    /**
     * Creates $count formation positions and returns their IDs.
     * @return int[]
     */
    private function createFormationPositions(int $formationId, int $count): array
    {
        $ids = [];
        for ($i = 1; $i <= $count; $i++) {
            $this->execute(
                "INSERT INTO formation_position (formation_id, label, line_key, grid_row, grid_col, sort_order)
                 VALUES (?, ?, 'MID', ?, 1, ?)",
                [$formationId, "Pos {$i}", $i, $i]
            );
            $ids[] = $this->lastInsertId();
        }
        return $ids;
    }
}

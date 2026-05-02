<?php

declare(strict_types=1);

namespace BarePitch\Tests\Feature\Match;

use BarePitch\Core\Exceptions\DomainException;
use BarePitch\Repositories\AuditRepository;
use BarePitch\Repositories\EventRepository;
use BarePitch\Repositories\LineupRepository;
use BarePitch\Repositories\LockRepository;
use BarePitch\Repositories\MatchRepository;
use BarePitch\Repositories\SelectionRepository;
use BarePitch\Services\AuditService;
use BarePitch\Services\LiveMatchService;
use BarePitch\Tests\Feature\FeatureTestCase;

/**
 * Tests LiveMatchService::startMatch() and LiveMatchService::finishMatch()
 * state-transition guard behaviour against a real DB.
 *
 * Covers:
 *   - Starting a planned match is rejected (only prepared can be started)
 *   - Starting a prepared match succeeds → status=active, period created
 *   - Finishing an already-finished match is rejected
 */
class MatchStateTest extends FeatureTestCase
{
    private LiveMatchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->buildService();
    }

    // ----------------------------------------------------------------
    // Starting a planned match must be rejected
    // ----------------------------------------------------------------

    public function testStartingPlannedMatchIsRejected(): void
    {
        $matchId = $this->createMatch(['status' => 'planned']);
        $match   = $this->fetchOne('SELECT * FROM `match` WHERE id = ?', [$matchId]);
        $user    = $this->loadUser(static::$coachId);

        $this->expectException(DomainException::class);
        $this->service->startMatch($user, $match);
    }

    // ----------------------------------------------------------------
    // Starting a prepared match succeeds
    // ----------------------------------------------------------------

    public function testStartingPreparedMatchSucceeds(): void
    {
        $matchId = $this->createMatch(['status' => 'prepared']);
        $playerId = $this->createPlayer('Starter', 'One');
        $this->createSelection($matchId, $playerId, ['is_starting' => 1]);
        $match   = $this->fetchOne('SELECT * FROM `match` WHERE id = ?', [$matchId]);
        $user    = $this->loadUser(static::$coachId);

        // Must not throw
        $this->service->startMatch($user, $match);

        // Status should be 'active'
        $updated = $this->fetchOne('SELECT status, active_phase FROM `match` WHERE id = ?', [$matchId]);
        $this->assertSame('active', $updated['status']);
        $this->assertSame('regular_time', $updated['active_phase']);

        // A period row must have been created
        $periods = $this->fetchAll('SELECT * FROM match_period WHERE match_id = ?', [$matchId]);
        $this->assertCount(1, $periods);
        $this->assertSame('regular_1', $periods[0]['period_key']);
        $this->assertNotNull($periods[0]['started_at']);

        // At least one selection must be activated on the field
        $activeSelections = $this->fetchAll(
            'SELECT id FROM match_selection WHERE match_id = ? AND is_active_on_field = 1',
            [$matchId]
        );
        $this->assertNotEmpty($activeSelections, 'Expected at least one selection with is_active_on_field = 1 after startMatch');
    }

    // ----------------------------------------------------------------
    // Finishing a finished match must be rejected
    // ----------------------------------------------------------------

    public function testFinishingFinishedMatchIsRejected(): void
    {
        $matchId = $this->createMatch(['status' => 'finished']);
        $match   = $this->fetchOne('SELECT * FROM `match` WHERE id = ?', [$matchId]);
        $user    = $this->loadUser(static::$coachId);

        $this->expectException(DomainException::class);
        $this->service->finishMatch($user, $match);
    }

    // ----------------------------------------------------------------
    // Finishing an active match (with a completed period) succeeds
    // ----------------------------------------------------------------

    public function testFinishingActiveMatchWithEndedPeriodSucceeds(): void
    {
        $matchId = $this->createMatch(['status' => 'active', 'active_phase' => 'halftime']);
        $match   = $this->fetchOne('SELECT * FROM `match` WHERE id = ?', [$matchId]);
        $user    = $this->loadUser(static::$coachId);

        // Insert a period that has ended (required by finishMatch)
        $this->execute(
            "INSERT INTO match_period (match_id, period_key, sort_order, started_at, ended_at, configured_duration_minutes)
             VALUES (?, 'regular_1', 1, NOW() - INTERVAL 50 MINUTE, NOW(), 45)",
            [$matchId]
        );

        // Must not throw
        $this->service->finishMatch($user, $match);

        $updated = $this->fetchOne('SELECT status, active_phase, finished_at FROM `match` WHERE id = ?', [$matchId]);
        $this->assertSame('finished', $updated['status']);
        $this->assertSame('finished', $updated['active_phase']);
        $this->assertNotNull($updated['finished_at']);
    }

    // ----------------------------------------------------------------
    // Finishing an active match with NO ended periods is rejected
    // ----------------------------------------------------------------

    public function testFinishingActiveMatchWithNoEndedPeriodIsRejected(): void
    {
        $matchId = $this->createMatch(['status' => 'active', 'active_phase' => 'regular_time']);
        $match   = $this->fetchOne('SELECT * FROM `match` WHERE id = ?', [$matchId]);
        $user    = $this->loadUser(static::$coachId);

        // Create a period that has been started but NOT ended
        $this->execute(
            "INSERT INTO match_period (match_id, period_key, sort_order, started_at, configured_duration_minutes)
             VALUES (?, 'regular_1', 1, NOW(), 45)",
            [$matchId]
        );

        $this->expectException(DomainException::class);
        $this->service->finishMatch($user, $match);
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    private function buildService(): LiveMatchService
    {
        $pdo           = static::$db;
        $matchRepo     = new MatchRepository($pdo);
        $selectionRepo = new SelectionRepository($pdo);
        $eventRepo     = new EventRepository($pdo);
        $lockRepo      = new LockRepository($pdo);
        $auditRepo     = new AuditRepository($pdo);
        $auditService  = new AuditService($auditRepo);

        return new LiveMatchService($matchRepo, $selectionRepo, $eventRepo, $lockRepo, $auditService);
    }
}

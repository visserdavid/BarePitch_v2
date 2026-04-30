<?php

declare(strict_types=1);

namespace BarePitch\Tests\Feature\Match;

use BarePitch\Repositories\AuditRepository;
use BarePitch\Repositories\EventRepository;
use BarePitch\Repositories\LockRepository;
use BarePitch\Repositories\MatchRepository;
use BarePitch\Repositories\SelectionRepository;
use BarePitch\Services\AuditService;
use BarePitch\Services\LiveMatchService;
use BarePitch\Tests\Feature\FeatureTestCase;

/**
 * Tests score integrity when goals are registered via LiveMatchService.
 *
 * Covers:
 *   - Own goal → goals_scored=1, goals_conceded=0
 *   - Opponent goal → goals_scored=0, goals_conceded=1
 *   - Mixed events → score totals are correct after re-read from DB
 */
class GoalIntegrityTest extends FeatureTestCase
{
    private LiveMatchService $service;
    private MatchRepository  $matchRepo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matchRepo = new MatchRepository(static::$db);
        $this->service   = $this->buildService();
    }

    // ----------------------------------------------------------------
    // Own goal → goals_scored increments
    // ----------------------------------------------------------------

    public function testOwnGoalIncrementsGoalsScored(): void
    {
        $matchId = $this->createActiveMatchWithPeriod();
        $match   = $this->matchRepo->findById($matchId);
        $user    = $this->loadUser(static::$coachId);

        $this->service->registerGoal($user, $match, ['team_side' => 'own']);

        $updated = $this->matchRepo->findById($matchId);
        $this->assertSame(1, (int) $updated['goals_scored']);
        $this->assertSame(0, (int) $updated['goals_conceded']);
    }

    // ----------------------------------------------------------------
    // Opponent goal → goals_conceded increments
    // ----------------------------------------------------------------

    public function testOpponentGoalIncrementsGoalsConceded(): void
    {
        $matchId = $this->createActiveMatchWithPeriod();
        $match   = $this->matchRepo->findById($matchId);
        $user    = $this->loadUser(static::$coachId);

        $this->service->registerOpponentGoal($user, $match, []);

        $updated = $this->matchRepo->findById($matchId);
        $this->assertSame(0, (int) $updated['goals_scored']);
        $this->assertSame(1, (int) $updated['goals_conceded']);
    }

    // ----------------------------------------------------------------
    // Score is correct after re-reading from DB
    // ----------------------------------------------------------------

    public function testScoreIsCorrectAfterRefresh(): void
    {
        $matchId = $this->createActiveMatchWithPeriod();
        $match   = $this->matchRepo->findById($matchId);
        $user    = $this->loadUser(static::$coachId);

        // 2 own goals, 1 opponent goal
        $this->service->registerGoal($user, $match, ['team_side' => 'own']);

        // Re-fetch match to keep the service's guard (status check) happy
        $match = $this->matchRepo->findById($matchId);
        $this->service->registerGoal($user, $match, ['team_side' => 'own']);

        $match = $this->matchRepo->findById($matchId);
        $this->service->registerOpponentGoal($user, $match, []);

        $updated = $this->matchRepo->findById($matchId);
        $this->assertSame(2, (int) $updated['goals_scored']);
        $this->assertSame(1, (int) $updated['goals_conceded']);
    }

    // ----------------------------------------------------------------
    // Event rows are created in match_event
    // ----------------------------------------------------------------

    public function testGoalCreatesEventRow(): void
    {
        $matchId = $this->createActiveMatchWithPeriod();
        $match   = $this->matchRepo->findById($matchId);
        $user    = $this->loadUser(static::$coachId);

        $this->service->registerGoal($user, $match, ['team_side' => 'own']);

        $events = $this->fetchAll(
            "SELECT * FROM match_event WHERE match_id = ? AND event_type = 'goal'",
            [$matchId]
        );
        $this->assertCount(1, $events);
        $this->assertSame('own', $events[0]['team_side']);
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    /**
     * Creates an active match and inserts an open period so that
     * LiveMatchService can find the current active period.
     */
    private function createActiveMatchWithPeriod(): int
    {
        $matchId = $this->createMatch([
            'status'       => 'active',
            'active_phase' => 'regular_time',
        ]);

        $this->execute(
            "INSERT INTO match_period (match_id, period_key, sort_order, started_at, configured_duration_minutes)
             VALUES (?, 'regular_1', 1, NOW(), 45)",
            [$matchId]
        );

        return $matchId;
    }

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

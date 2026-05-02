<?php

declare(strict_types=1);

namespace BarePitch\Tests\Feature\Match;

use BarePitch\Core\Exceptions\DomainException;
use BarePitch\Core\Request;
use BarePitch\Http\Controllers\MatchController;
use BarePitch\Repositories\AuditRepository;
use BarePitch\Repositories\EventRepository;
use BarePitch\Repositories\LineupRepository;
use BarePitch\Repositories\LockRepository;
use BarePitch\Repositories\MatchRepository;
use BarePitch\Repositories\PhaseRepository;
use BarePitch\Repositories\SelectionRepository;
use BarePitch\Repositories\UserRepository;
use BarePitch\Services\AuditService;
use BarePitch\Services\AuthService;
use BarePitch\Services\LiveMatchService;
use BarePitch\Services\MatchService;
use BarePitch\Services\TeamContextService;
use BarePitch\Repositories\TeamRepository;
use BarePitch\Tests\Feature\FeatureTestCase;

/**
 * Tests the "summary" view (match show page for a finished match) and
 * the guard that prevents re-starting a finished match.
 *
 * Because View rendering requires the full HTTP bootstrap (templates, output),
 * we test the view-loads aspect by verifying the DB state is loadable and
 * complete — not by rendering HTML. The actual rendering is a view concern.
 *
 * For "restarting a finished match", we test LiveMatchService::startMatch()
 * which is the service-layer guard, and MatchPolicy::canStart() which is the
 * policy-layer guard. Both must reject a finished match.
 */
class MatchFinishTest extends FeatureTestCase
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
    // Summary view data loads correctly
    // ----------------------------------------------------------------

    public function testFinishedMatchDataIsLoadable(): void
    {
        $matchId = $this->createMatch([
            'status'       => 'finished',
            'active_phase' => 'finished',
            'goals_scored' => 2,
            'goals_conceded' => 1,
        ]);

        $match = $this->matchRepo->findById($matchId);

        $this->assertNotNull($match);
        $this->assertSame('finished', $match['status']);
        $this->assertSame(2, (int) $match['goals_scored']);
        $this->assertSame(1, (int) $match['goals_conceded']);
    }

    public function testFinishedMatchIncludesEventHistory(): void
    {
        $matchId = $this->createMatch([
            'status'       => 'finished',
            'active_phase' => 'finished',
        ]);

        // Insert a goal event manually
        $this->execute(
            "INSERT INTO match_event
             (match_id, event_type, team_side, outcome, created_by_user_id)
             VALUES (?, 'goal', 'own', 'none', ?)",
            [$matchId, static::$coachId]
        );

        $eventRepo = new EventRepository(static::$db);
        $events    = $eventRepo->findByMatch($matchId);

        $this->assertCount(1, $events);
        $this->assertSame('goal', $events[0]['event_type']);
    }

    public function testSummaryRouteLoadsFinishedMatchSummaryView(): void
    {
        $matchId = $this->createMatch([
            'status'       => 'finished',
            'active_phase' => 'finished',
            'goals_scored' => 3,
            'goals_conceded' => 2,
            'opponent_name' => 'Summary FC',
        ]);

        $_SESSION['user_id'] = static::$coachId;
        $_SESSION['active_team_id'] = static::$teamId;

        $controller = $this->buildMatchController();

        ob_start();
        $controller->summary(new Request(), ['match_id' => (string) $matchId]);
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('Summary FC', $html);
        $this->assertStringContainsString('3', $html);
        $this->assertStringContainsString('2', $html);
    }

    // ----------------------------------------------------------------
    // Re-starting a finished match is rejected at the service layer
    // ----------------------------------------------------------------

    public function testRestartingFinishedMatchIsRejectedByService(): void
    {
        $matchId = $this->createMatch(['status' => 'finished', 'active_phase' => 'finished']);
        $match   = $this->matchRepo->findById($matchId);
        $user    = $this->loadUser(static::$coachId);

        $this->expectException(DomainException::class);
        $this->service->startMatch($user, $match);
    }

    // ----------------------------------------------------------------
    // Policy rejects starting a finished match
    // ----------------------------------------------------------------

    public function testRestartingFinishedMatchIsRejectedByPolicy(): void
    {
        $match = [
            'id'      => 1,
            'team_id' => static::$teamId,
            'status'  => 'finished',
        ];

        $user = [
            'id'               => static::$coachId,
            'is_administrator' => 0,
            'roles'            => [
                ['team_id' => static::$teamId, 'role_key' => 'coach'],
            ],
        ];

        $this->expectException(\BarePitch\Core\Exceptions\AuthorizationException::class);
        \BarePitch\Policies\MatchPolicy::canStart($user, $match);
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

    private function buildMatchController(): MatchController
    {
        $pdo          = static::$db;
        $auditRepo    = new AuditRepository($pdo);
        $auditService = new AuditService($auditRepo);

        return new MatchController(
            new AuthService(new UserRepository($pdo)),
            new TeamContextService(new TeamRepository($pdo)),
            new MatchRepository($pdo),
            new MatchService(new MatchRepository($pdo), $auditService),
            new SelectionRepository($pdo),
            new LineupRepository($pdo),
            new EventRepository($pdo),
            new PhaseRepository($pdo)
        );
    }
}

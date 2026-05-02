<?php

declare(strict_types=1);

namespace BarePitch\Tests\Feature\Match;

use BarePitch\Core\Exceptions\DomainException;
use BarePitch\Core\Request;
use BarePitch\Http\Controllers\LiveMatchController;
use BarePitch\Http\Controllers\MatchController;
use BarePitch\Http\Controllers\MatchPreparationController;
use BarePitch\Repositories\AuditRepository;
use BarePitch\Repositories\EventRepository;
use BarePitch\Repositories\LineupRepository;
use BarePitch\Repositories\LockRepository;
use BarePitch\Repositories\MatchRepository;
use BarePitch\Repositories\PhaseRepository;
use BarePitch\Repositories\PlayerRepository;
use BarePitch\Repositories\SelectionRepository;
use BarePitch\Repositories\TeamRepository;
use BarePitch\Repositories\UserRepository;
use BarePitch\Services\AuditService;
use BarePitch\Services\AuthService;
use BarePitch\Services\LiveMatchService;
use BarePitch\Services\MatchPreparationService;
use BarePitch\Services\MatchService;
use BarePitch\Services\TeamContextService;
use BarePitch\Tests\Feature\FeatureTestCase;

class MatchControllerDomainErrorTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(static::$coachId);
        $_SESSION['active_team_id'] = static::$teamId;
        $_SESSION['_csrf'] = 'test-token';
    }

    public function testStartDomainFailureRendersSafeError(): void
    {
        $matchId = $this->createMatch(['status' => 'prepared']);
        $_POST = ['_csrf' => 'test-token', 'confirm' => '1'];

        $html = $this->capture(fn() => $this->liveController('start failed safely')->start(
            new Request(),
            ['match_id' => (string) $matchId]
        ));

        $this->assertStringContainsString('start failed safely', $html);
    }

    public function testFinishDomainFailureRendersSafeError(): void
    {
        $matchId = $this->createMatch(['status' => 'active']);
        $_POST = ['_csrf' => 'test-token', 'confirm' => '1'];

        $html = $this->capture(fn() => $this->liveController('finish failed safely')->finish(
            new Request(),
            ['match_id' => (string) $matchId]
        ));

        $this->assertStringContainsString('finish failed safely', $html);
    }

    public function testGoalDomainFailureRendersSafeError(): void
    {
        $matchId = $this->createMatch(['status' => 'active']);
        $playerId = $this->createPlayer('Goal', 'Scorer');
        $selectionId = $this->createSelection($matchId, $playerId);
        $_POST = [
            '_csrf' => 'test-token',
            'team_side' => 'own',
            'player_selection_id' => (string) $selectionId,
            'minute_display' => '12',
        ];

        $html = $this->capture(fn() => $this->liveController('goal failed safely')->registerGoal(
            new Request(),
            ['match_id' => (string) $matchId]
        ));

        $this->assertStringContainsString('goal failed safely', $html);
    }

    public function testSetFormationDomainFailureRendersSafeError(): void
    {
        $matchId = $this->createMatch(['status' => 'planned']);
        $_POST = ['_csrf' => 'test-token', 'formation_id' => '1'];

        $html = $this->capture(fn() => $this->prepController('formation failed safely')->setFormation(
            new Request(),
            ['match_id' => (string) $matchId]
        ));

        $this->assertStringContainsString('formation failed safely', $html);
    }

    public function testMatchUpdateDomainFailureRendersSafeError(): void
    {
        $matchId = $this->createMatch(['status' => 'active']);
        $_POST = [
            '_csrf' => 'test-token',
            'phase_id' => (string) static::$phaseId,
            'date' => '2026-05-02',
            'opponent_name' => 'Domain FC',
            'home_away' => 'home',
            'match_type' => 'league',
            'regular_half_duration_minutes' => '45',
        ];

        $html = $this->capture(fn() => $this->matchController('update failed safely')->update(
            new Request(),
            ['match_id' => (string) $matchId]
        ));

        $this->assertStringContainsString('update failed safely', $html);
    }

    protected function tearDown(): void
    {
        $_POST = [];
        parent::tearDown();
    }

    private function capture(callable $callback): string
    {
        ob_start();
        $callback();
        return (string) ob_get_clean();
    }

    private function liveController(string $message): LiveMatchController
    {
        $pdo = static::$db;
        return new LiveMatchController(
            new AuthService(new UserRepository($pdo)),
            new TeamContextService(new TeamRepository($pdo)),
            new MatchRepository($pdo),
            new SelectionRepository($pdo),
            new EventRepository($pdo),
            new LineupRepository($pdo),
            new ThrowingLiveMatchService($message)
        );
    }

    private function prepController(string $message): MatchPreparationController
    {
        $pdo = static::$db;
        return new MatchPreparationController(
            new AuthService(new UserRepository($pdo)),
            new TeamContextService(new TeamRepository($pdo)),
            new MatchRepository($pdo),
            new PlayerRepository($pdo),
            new SelectionRepository($pdo),
            new LineupRepository($pdo),
            new TeamRepository($pdo),
            new ThrowingMatchPreparationService($message)
        );
    }

    private function matchController(string $message): MatchController
    {
        $pdo = static::$db;
        return new MatchController(
            new AuthService(new UserRepository($pdo)),
            new TeamContextService(new TeamRepository($pdo)),
            new MatchRepository($pdo),
            new ThrowingMatchService($message),
            new SelectionRepository($pdo),
            new LineupRepository($pdo),
            new EventRepository($pdo),
            new PhaseRepository($pdo)
        );
    }
}

class ThrowingLiveMatchService extends LiveMatchService
{
    public function __construct(private readonly string $message) {}

    public function startMatch(array $user, array $match): void
    {
        throw new DomainException($this->message);
    }

    public function finishMatch(array $user, array $match): void
    {
        throw new DomainException($this->message);
    }

    public function registerGoal(array $user, array $match, array $data): int
    {
        throw new DomainException($this->message);
    }
}

class ThrowingMatchPreparationService extends MatchPreparationService
{
    public function __construct(private readonly string $message) {}

    public function setFormation(array $user, array $match, int $formationId): void
    {
        throw new DomainException($this->message);
    }
}

class ThrowingMatchService extends MatchService
{
    public function __construct(private readonly string $message) {}

    public function update(array $user, array $match, array $data): void
    {
        throw new DomainException($this->message);
    }
}

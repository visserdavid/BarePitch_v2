<?php

declare(strict_types=1);

namespace BarePitch\Http\Controllers;

use BarePitch\Core\Csrf;
use BarePitch\Core\Request;
use BarePitch\Core\Response;
use BarePitch\Core\View;
use BarePitch\Core\Exceptions\NotFoundException;
use BarePitch\Core\Exceptions\ValidationException;
use BarePitch\Http\Requests\RegisterGoalRequest;
use BarePitch\Http\Requests\StartMatchRequest;
use BarePitch\Http\Requests\FinishMatchRequest;
use BarePitch\Policies\MatchPolicy;
use BarePitch\Repositories\EventRepository;
use BarePitch\Repositories\LineupRepository;
use BarePitch\Repositories\MatchRepository;
use BarePitch\Repositories\SelectionRepository;
use BarePitch\Services\AuthService;
use BarePitch\Services\LiveMatchService;
use BarePitch\Services\TeamContextService;

class LiveMatchController
{
    public function __construct(
        private readonly AuthService        $auth,
        private readonly TeamContextService $teamContext,
        private readonly MatchRepository    $matches,
        private readonly SelectionRepository $selections,
        private readonly EventRepository    $events,
        private readonly LineupRepository   $lineup,
        private readonly LiveMatchService   $liveService,
    ) {}

    public function show(Request $request, array $params = []): void
    {
        $user  = $this->auth->requireAuth();
        $team  = $this->teamContext->requireTeamContext($user);
        $match = $this->matches->findByIdForTeam((int) ($params['match_id'] ?? 0), (int) $team['id']);

        if (!$match) {
            throw new NotFoundException('Match not found.');
        }

        MatchPolicy::canView($user, $match);

        $this->renderLiveView($user, $team, $match, []);
    }

    public function start(Request $request, array $params = []): void
    {
        $user  = $this->auth->requireAuth();
        $team  = $this->teamContext->requireTeamContext($user);
        $match = $this->matches->findByIdForTeam((int) ($params['match_id'] ?? 0), (int) $team['id']);

        if (!$match) {
            throw new NotFoundException('Match not found.');
        }

        MatchPolicy::canStart($user, $match);

        Csrf::verify($request);

        try {
            StartMatchRequest::validate($request);
            $this->liveService->startMatch($user, $match);
        } catch (ValidationException $e) {
            $this->renderLiveView($user, $team, $match, $e->getErrors());
            return;
        }

        Response::redirect('/matches/' . $match['id'] . '/live');
    }

    public function finish(Request $request, array $params = []): void
    {
        $user  = $this->auth->requireAuth();
        $team  = $this->teamContext->requireTeamContext($user);
        $match = $this->matches->findByIdForTeam((int) ($params['match_id'] ?? 0), (int) $team['id']);

        if (!$match) {
            throw new NotFoundException('Match not found.');
        }

        MatchPolicy::canFinish($user, $match);

        Csrf::verify($request);

        try {
            FinishMatchRequest::validate($request);
            $this->liveService->finishMatch($user, $match);
        } catch (ValidationException $e) {
            $this->renderLiveView($user, $team, $match, $e->getErrors());
            return;
        }

        Response::redirect('/matches/' . $match['id']);
    }

    public function endPeriod(Request $request, array $params = []): void
    {
        $user     = $this->auth->requireAuth();
        $team     = $this->teamContext->requireTeamContext($user);
        $match    = $this->matches->findByIdForTeam((int) ($params['match_id'] ?? 0), (int) $team['id']);
        $periodId = (int) ($params['period_id'] ?? 0);

        if (!$match) {
            throw new NotFoundException('Match not found.');
        }

        MatchPolicy::canRegisterLiveEvent($user, $match);

        Csrf::verify($request);

        try {
            $this->liveService->endPeriod($user, $match, $periodId);
        } catch (\BarePitch\Core\Exceptions\DomainException $e) {
            $this->renderLiveView($user, $team, $match, ['period' => $e->getMessage()]);
            return;
        }

        Response::redirect('/matches/' . $match['id'] . '/live');
    }

    public function startSecondHalf(Request $request, array $params = []): void
    {
        $user  = $this->auth->requireAuth();
        $team  = $this->teamContext->requireTeamContext($user);
        $match = $this->matches->findByIdForTeam((int) ($params['match_id'] ?? 0), (int) $team['id']);

        if (!$match) {
            throw new NotFoundException('Match not found.');
        }

        MatchPolicy::canRegisterLiveEvent($user, $match);

        Csrf::verify($request);

        try {
            $this->liveService->startSecondHalf($user, $match);
        } catch (\BarePitch\Core\Exceptions\DomainException $e) {
            $this->renderLiveView($user, $team, $match, ['period' => $e->getMessage()]);
            return;
        }

        Response::redirect('/matches/' . $match['id'] . '/live');
    }

    public function registerGoal(Request $request, array $params = []): void
    {
        $user  = $this->auth->requireAuth();
        $team  = $this->teamContext->requireTeamContext($user);
        $match = $this->matches->findByIdForTeam((int) ($params['match_id'] ?? 0), (int) $team['id']);

        if (!$match) {
            throw new NotFoundException('Match not found.');
        }

        MatchPolicy::canRegisterLiveEvent($user, $match);

        Csrf::verify($request);

        try {
            $data = RegisterGoalRequest::validate($request);
            $this->liveService->registerGoal($user, $match, $data);
        } catch (ValidationException $e) {
            $this->renderLiveView($user, $team, $match, $e->getErrors());
            return;
        }

        Response::redirect('/matches/' . $match['id'] . '/live');
    }

    private function renderLiveView(array $user, array $team, array $match, array $errors): void
    {
        $events  = $this->events->findByMatch((int) $match['id']);
        $periods = $this->matches->findPeriods((int) $match['id']);
        $players = $this->selections->findPresentByMatch((int) $match['id']);

        // Determine the current (most recent started) period
        $period = null;
        foreach (array_reverse($periods) as $p) {
            if ($p['started_at'] !== null) {
                $period = $p;
                break;
            }
        }

        echo View::layout('matches/live', [
            'match'   => $match,
            'team'    => $team,
            'period'  => $period,
            'events'  => $events,
            'players' => $players,
            'errors'  => $errors,
            'user'    => $user,
        ]);
    }
}

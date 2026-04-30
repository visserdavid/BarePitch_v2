<?php

declare(strict_types=1);

namespace BarePitch\Http\Controllers;

use BarePitch\Core\Csrf;
use BarePitch\Core\Request;
use BarePitch\Core\Response;
use BarePitch\Core\View;
use BarePitch\Core\Exceptions\NotFoundException;
use BarePitch\Core\Exceptions\ValidationException;
use BarePitch\Http\Requests\CreateMatchRequest;
use BarePitch\Policies\MatchPolicy;
use BarePitch\Repositories\EventRepository;
use BarePitch\Repositories\LineupRepository;
use BarePitch\Repositories\MatchRepository;
use BarePitch\Repositories\PhaseRepository;
use BarePitch\Repositories\SelectionRepository;
use BarePitch\Services\AuthService;
use BarePitch\Services\MatchService;
use BarePitch\Services\TeamContextService;

class MatchController
{
    public function __construct(
        private readonly AuthService        $auth,
        private readonly TeamContextService $teamContext,
        private readonly MatchRepository    $matches,
        private readonly MatchService       $matchService,
        private readonly SelectionRepository $selections,
        private readonly LineupRepository   $lineup,
        private readonly EventRepository    $events,
        private readonly PhaseRepository    $phases,
    ) {}

    public function index(Request $request, array $params = []): void
    {
        $user    = $this->auth->requireAuth();
        $team    = $this->teamContext->requireTeamContext($user);
        $matches = $this->matches->findByTeam((int) $team['id']);

        echo View::layout('matches/index', [
            'matches' => $matches,
            'team'    => $team,
            'user'    => $user,
        ]);
    }

    public function create(Request $request, array $params = []): void
    {
        $user = $this->auth->requireAuth();
        $team = $this->teamContext->requireTeamContext($user);

        MatchPolicy::canCreate($user, $team);

        $phases = $this->phases->findByTeam((int) $team['id']);

        echo View::layout('matches/create', [
            'team'   => $team,
            'phases' => $phases,
            'errors' => [],
            'old'    => [],
            'user'   => $user,
        ]);
    }

    public function store(Request $request, array $params = []): void
    {
        $user = $this->auth->requireAuth();
        $team = $this->teamContext->requireTeamContext($user);

        MatchPolicy::canCreate($user, $team);

        Csrf::verify($request);

        try {
            $data    = CreateMatchRequest::validate($request);
            $matchId = $this->matchService->create($user, $team, $data);
        } catch (ValidationException $e) {
            $phases = $this->phases->findByTeam((int) $team['id']);
            echo View::layout('matches/create', [
                'team'   => $team,
                'phases' => $phases,
                'errors' => $e->getErrors(),
                'old'    => $request->all(),
                'user'   => $user,
            ]);
            return;
        }

        Response::redirect('/matches/' . $matchId);
    }

    public function show(Request $request, array $params = []): void
    {
        $user  = $this->auth->requireAuth();
        $team  = $this->teamContext->requireTeamContext($user);
        $match = $this->matches->findByIdForTeam((int) ($params['match_id'] ?? 0), (int) $team['id']);

        if (!$match) {
            throw new NotFoundException('Match not found.');
        }

        MatchPolicy::canView($user, $match);

        // Load supporting data for the sub-view (show delegates to prepare/live/summary)
        $selections  = $this->selections->findByMatch((int) $match['id']);
        $lineupSlots = $this->lineup->findByMatch((int) $match['id']);
        $events      = $this->events->findByMatch((int) $match['id']);
        $periods     = $this->matches->findPeriods((int) $match['id']);
        $players     = $this->selections->findPresentByMatch((int) $match['id']);

        // Determine current period for live view
        $period = null;
        foreach (array_reverse($periods) as $p) {
            if ($p['started_at'] !== null) {
                $period = $p;
                break;
            }
        }

        echo View::layout('matches/show', [
            'match'       => $match,
            'team'        => $team,
            'selections'  => $selections,
            'lineupSlots' => $lineupSlots,
            'events'      => $events,
            'periods'     => $periods,
            'period'      => $period,
            'players'     => $players,
            'formations'  => [],
            'errors'      => [],
            'user'        => $user,
        ]);
    }

    public function edit(Request $request, array $params = []): void
    {
        $user  = $this->auth->requireAuth();
        $team  = $this->teamContext->requireTeamContext($user);
        $match = $this->matches->findByIdForTeam((int) ($params['match_id'] ?? 0), (int) $team['id']);

        if (!$match) {
            throw new NotFoundException('Match not found.');
        }

        MatchPolicy::canEdit($user, $match);

        $phases = $this->phases->findByTeam((int) $team['id']);

        echo View::layout('matches/create', [
            'match'  => $match,
            'team'   => $team,
            'phases' => $phases,
            'errors' => [],
            'old'    => $match,
            'user'   => $user,
        ]);
    }

    public function update(Request $request, array $params = []): void
    {
        $user  = $this->auth->requireAuth();
        $team  = $this->teamContext->requireTeamContext($user);
        $match = $this->matches->findByIdForTeam((int) ($params['match_id'] ?? 0), (int) $team['id']);

        if (!$match) {
            throw new NotFoundException('Match not found.');
        }

        MatchPolicy::canEdit($user, $match);

        Csrf::verify($request);

        try {
            $data = CreateMatchRequest::validate($request);
            $this->matchService->update($user, $match, $data);
        } catch (ValidationException $e) {
            $phases = $this->phases->findByTeam((int) $team['id']);
            echo View::layout('matches/create', [
                'match'  => $match,
                'team'   => $team,
                'phases' => $phases,
                'errors' => $e->getErrors(),
                'old'    => $request->all(),
                'user'   => $user,
            ]);
            return;
        }

        Response::redirect('/matches/' . $match['id']);
    }

}

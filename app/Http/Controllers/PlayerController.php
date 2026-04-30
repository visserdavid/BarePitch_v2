<?php

declare(strict_types=1);

namespace BarePitch\Http\Controllers;

use BarePitch\Core\Csrf;
use BarePitch\Core\Request;
use BarePitch\Core\Response;
use BarePitch\Core\View;
use BarePitch\Core\Exceptions\NotFoundException;
use BarePitch\Core\Exceptions\ValidationException;
use BarePitch\Http\Requests\CreatePlayerRequest;
use BarePitch\Policies\PlayerPolicy;
use BarePitch\Repositories\PlayerRepository;
use BarePitch\Services\AuthService;
use BarePitch\Services\PlayerService;
use BarePitch\Services\TeamContextService;

class PlayerController
{
    public function __construct(
        private readonly AuthService        $auth,
        private readonly TeamContextService $teamContext,
        private readonly PlayerRepository   $players,
        private readonly PlayerService      $playerService,
    ) {}

    public function index(Request $request, array $params = []): void
    {
        $user = $this->auth->requireAuth();
        $team = $this->teamContext->requireTeamContext($user);

        PlayerPolicy::canView($user, $team);

        // Load players for the team — use season from team if available
        $seasonId = (int) ($team['season_id'] ?? 0);
        $players  = $seasonId > 0
            ? $this->players->findActiveByTeamAndSeason((int) $team['id'], $seasonId)
            : [];

        echo View::layout('players/index', [
            'players' => $players,
            'team'    => $team,
            'user'    => $user,
        ]);
    }

    public function create(Request $request, array $params = []): void
    {
        $user = $this->auth->requireAuth();
        $team = $this->teamContext->requireTeamContext($user);

        PlayerPolicy::canCreate($user, $team);

        echo View::layout('players/create', [
            'team'   => $team,
            'errors' => [],
            'old'    => [],
            'user'   => $user,
        ]);
    }

    public function store(Request $request, array $params = []): void
    {
        $user = $this->auth->requireAuth();
        $team = $this->teamContext->requireTeamContext($user);

        PlayerPolicy::canCreate($user, $team);

        Csrf::verify($request);

        try {
            $data = CreatePlayerRequest::validate($request);
        } catch (ValidationException $e) {
            echo View::layout('players/create', [
                'team'   => $team,
                'errors' => $e->getErrors(),
                'old'    => $request->all(),
                'user'   => $user,
            ]);
            return;
        }

        $playerId = $this->playerService->create($user, $team, [
            'first_name'     => $data['first_name'],
            'last_name'      => $data['last_name'],
            'display_name'   => $data['display_name'],
            'squad_number'   => $data['shirt_number'],
            'preferred_line' => $data['position_line'],
            'preferred_foot' => null,
        ]);

        Response::redirect('/players/' . $playerId);
    }

    public function show(Request $request, array $params = []): void
    {
        $user = $this->auth->requireAuth();
        $team = $this->teamContext->requireTeamContext($user);

        PlayerPolicy::canView($user, $team);

        $seasonId = (int) ($team['season_id'] ?? 0);
        if ($seasonId === 0) {
            throw new NotFoundException('Player not found.');
        }

        $player = $this->players->findByIdForTeam(
            (int) ($params['player_id'] ?? 0),
            (int) $team['id'],
            $seasonId
        );

        if (!$player) {
            throw new NotFoundException('Player not found.');
        }

        $seasonContext = $this->players->findSeasonContext((int) $player['id'], $seasonId);

        echo View::layout('players/show', [
            'player'        => $player,
            'seasonContext' => $seasonContext,
            'matchHistory'  => [],
            'team'          => $team,
            'user'          => $user,
        ]);
    }
}

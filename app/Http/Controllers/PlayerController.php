<?php

declare(strict_types=1);

namespace BarePitch\Http\Controllers;

use BarePitch\Core\Csrf;
use BarePitch\Core\Request;
use BarePitch\Core\Response;
use BarePitch\Core\View;
use BarePitch\Core\Exceptions\NotFoundException;
use BarePitch\Core\Exceptions\ValidationException;
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
        $seasonId = (int) ($team['current_season_id'] ?? 0);
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

        $firstName   = trim((string) $request->post('first_name', ''));
        $lastName    = trim((string) $request->post('last_name', ''));
        $displayName = trim((string) $request->post('display_name', ''));

        $errors = [];
        if ($firstName === '') {
            $errors['first_name'] = 'First name is required.';
        } elseif (mb_strlen($firstName) > 100) {
            $errors['first_name'] = 'First name must not exceed 100 characters.';
        }
        if ($lastName === '') {
            $errors['last_name'] = 'Last name is required.';
        } elseif (mb_strlen($lastName) > 100) {
            $errors['last_name'] = 'Last name must not exceed 100 characters.';
        }
        if ($displayName !== '' && mb_strlen($displayName) > 100) {
            $errors['display_name'] = 'Display name must not exceed 100 characters.';
        }

        if ($errors !== []) {
            echo View::layout('players/create', [
                'team'   => $team,
                'errors' => $errors,
                'old'    => $request->all(),
                'user'   => $user,
            ]);
            return;
        }

        $squadNumber   = $request->post('squad_number');
        $preferredLine = trim((string) $request->post('preferred_line', ''));
        $preferredFoot = trim((string) $request->post('preferred_foot', ''));

        $playerId = $this->playerService->create($user, $team, [
            'first_name'    => $firstName,
            'last_name'     => $lastName,
            'display_name'  => $displayName !== '' ? $displayName : null,
            'squad_number'  => ($squadNumber !== null && $squadNumber !== '') ? (int) $squadNumber : null,
            'preferred_line' => $preferredLine !== '' ? $preferredLine : null,
            'preferred_foot' => $preferredFoot !== '' ? $preferredFoot : null,
        ]);

        Response::redirect('/players/' . $playerId);
    }

    public function show(Request $request, array $params = []): void
    {
        $user   = $this->auth->requireAuth();
        $team   = $this->teamContext->requireTeamContext($user);
        $player = $this->players->findById((int) ($params['player_id'] ?? 0));

        if (!$player) {
            throw new NotFoundException('Player not found.');
        }

        PlayerPolicy::canView($user, $team);

        $seasonId      = (int) ($team['current_season_id'] ?? 0);
        $seasonContext = $seasonId > 0
            ? $this->players->findSeasonContext((int) $player['id'], $seasonId)
            : null;

        echo View::layout('players/show', [
            'player'        => $player,
            'seasonContext' => $seasonContext,
            'matchHistory'  => [],
            'team'          => $team,
            'user'          => $user,
        ]);
    }
}

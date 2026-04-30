<?php

declare(strict_types=1);

namespace BarePitch\Http\Controllers;

use BarePitch\Core\Request;
use BarePitch\Core\Response;
use BarePitch\Core\View;
use BarePitch\Repositories\MatchRepository;
use BarePitch\Repositories\TeamRepository;
use BarePitch\Repositories\UserRepository;
use BarePitch\Services\AuthService;
use BarePitch\Services\TeamContextService;

class HomeController
{
    public function __construct(
        private readonly AuthService        $auth,
        private readonly TeamContextService $teamContext,
        private readonly MatchRepository    $matches,
        private readonly TeamRepository     $teams,
        private readonly UserRepository     $users,
    ) {}

    public function index(Request $request, array $params = []): void
    {
        $user = $this->auth->requireAuth();
        $team = $this->teamContext->getActiveTeam($user);

        $recentMatches = [];
        if ($team !== null) {
            $allMatches    = $this->matches->findByTeam((int) $team['id']);
            $recentMatches = array_slice($allMatches, 0, 5);
        }

        $isAdmin    = (int) ($user['is_administrator'] ?? 0) === 1;
        $accessible = $this->teams->findAccessibleForUser((int) $user['id'], $isAdmin);

        echo View::layout('home/index', [
            'team'          => $team,
            'recentMatches' => $recentMatches,
            'teams'         => $accessible,
            'user'          => $user,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace BarePitch\Http\Controllers;

use BarePitch\Core\Csrf;
use BarePitch\Core\Request;
use BarePitch\Core\Response;
use BarePitch\Core\Exceptions\AuthorizationException;
use BarePitch\Services\AuthService;
use BarePitch\Services\TeamContextService;

class ContextController
{
    public function __construct(
        private readonly AuthService        $auth,
        private readonly TeamContextService $teamContext,
    ) {}

    public function switchTeam(Request $request, array $params = []): void
    {
        $user = $this->auth->requireAuth();

        Csrf::verify($request);

        $teamIdRaw = $request->post('team_id');
        $teamId    = ($teamIdRaw !== null && $teamIdRaw !== '') ? (int) $teamIdRaw : null;

        if ($teamId === null || $teamId < 1) {
            Response::redirect('/');
        }

        try {
            $this->teamContext->setActiveTeam($teamId, $user);
        } catch (AuthorizationException | \InvalidArgumentException $e) {
            Response::redirect('/');
        }

        $redirect = (string) $request->post('redirect', '/');
        Response::redirect($redirect ?: '/');
    }
}

<?php

declare(strict_types=1);

namespace BarePitch\Http\Controllers;

use BarePitch\Core\Csrf;
use BarePitch\Core\Request;
use BarePitch\Core\Response;
use BarePitch\Core\View;
use BarePitch\Core\Exceptions\DomainException;
use BarePitch\Core\Exceptions\NotFoundException;
use BarePitch\Core\Exceptions\ValidationException;
use BarePitch\Http\Requests\SaveAttendanceRequest;
use BarePitch\Http\Requests\SetFormationRequest;
use BarePitch\Http\Requests\SaveLineupRequest;
use BarePitch\Policies\MatchPolicy;
use BarePitch\Repositories\LineupRepository;
use BarePitch\Repositories\MatchRepository;
use BarePitch\Repositories\PlayerRepository;
use BarePitch\Repositories\SelectionRepository;
use BarePitch\Repositories\TeamRepository;
use BarePitch\Services\AuthService;
use BarePitch\Services\MatchPreparationService;
use BarePitch\Services\TeamContextService;

class MatchPreparationController
{
    public function __construct(
        private readonly AuthService              $auth,
        private readonly TeamContextService       $teamContext,
        private readonly MatchRepository          $matches,
        private readonly PlayerRepository         $players,
        private readonly SelectionRepository      $selections,
        private readonly LineupRepository         $lineup,
        private readonly TeamRepository           $teams,
        private readonly MatchPreparationService  $prepService,
    ) {}

    public function show(Request $request, array $params = []): void
    {
        $user  = $this->auth->requireAuth();
        $team  = $this->teamContext->requireTeamContext($user);
        $match = $this->matches->findByIdForTeam((int) ($params['match_id'] ?? 0), (int) $team['id']);

        if (!$match) {
            throw new NotFoundException('Match not found.');
        }

        MatchPolicy::canPrepare($user, $match);

        $this->renderPrepView($user, $team, $match, []);
    }

    public function saveAttendance(Request $request, array $params = []): void
    {
        $user  = $this->auth->requireAuth();
        $team  = $this->teamContext->requireTeamContext($user);
        $match = $this->matches->findByIdForTeam((int) ($params['match_id'] ?? 0), (int) $team['id']);

        if (!$match) {
            throw new NotFoundException('Match not found.');
        }

        MatchPolicy::canPrepare($user, $match);

        Csrf::verify($request);

        try {
            $data = SaveAttendanceRequest::validate($request);
            $this->prepService->saveAttendance($user, $match, $data['attendance']);
        } catch (ValidationException $e) {
            $this->renderPrepView($user, $team, $match, $e->getErrors());
            return;
        } catch (DomainException $e) {
            $this->renderPrepView($user, $team, $match, ['attendance' => $e->getMessage()]);
            return;
        }

        Response::redirect('/matches/' . $match['id'] . '/prepare');
    }

    public function setFormation(Request $request, array $params = []): void
    {
        $user  = $this->auth->requireAuth();
        $team  = $this->teamContext->requireTeamContext($user);
        $match = $this->matches->findByIdForTeam((int) ($params['match_id'] ?? 0), (int) $team['id']);

        if (!$match) {
            throw new NotFoundException('Match not found.');
        }

        MatchPolicy::canPrepare($user, $match);

        Csrf::verify($request);

        try {
            $data = SetFormationRequest::validate($request);
            $this->prepService->setFormation($user, $match, $data['formation_id']);
        } catch (ValidationException $e) {
            $this->renderPrepView($user, $team, $match, $e->getErrors());
            return;
        } catch (DomainException $e) {
            $this->renderPrepView($user, $team, $match, ['formation_id' => $e->getMessage()]);
            return;
        }

        Response::redirect('/matches/' . $match['id'] . '/prepare');
    }

    public function saveLineup(Request $request, array $params = []): void
    {
        $user  = $this->auth->requireAuth();
        $team  = $this->teamContext->requireTeamContext($user);
        $match = $this->matches->findByIdForTeam((int) ($params['match_id'] ?? 0), (int) $team['id']);

        if (!$match) {
            throw new NotFoundException('Match not found.');
        }

        MatchPolicy::canPrepare($user, $match);

        Csrf::verify($request);

        try {
            $data = SaveLineupRequest::validate($request);
            $this->prepService->saveLineup($user, $match, $data['slots']);
        } catch (ValidationException $e) {
            $this->renderPrepView($user, $team, $match, $e->getErrors());
            return;
        } catch (DomainException $e) {
            $this->renderPrepView($user, $team, $match, ['lineup' => $e->getMessage()]);
            return;
        }

        Response::redirect('/matches/' . $match['id'] . '/prepare');
    }

    public function confirmPreparation(Request $request, array $params = []): void
    {
        $user  = $this->auth->requireAuth();
        $team  = $this->teamContext->requireTeamContext($user);
        $match = $this->matches->findByIdForTeam((int) ($params['match_id'] ?? 0), (int) $team['id']);

        if (!$match) {
            throw new NotFoundException('Match not found.');
        }

        MatchPolicy::canPrepare($user, $match);

        Csrf::verify($request);

        try {
            $this->prepService->confirmPreparation($user, $match);
        } catch (ValidationException $e) {
            $this->renderPrepView($user, $team, $match, $e->getErrors());
            return;
        } catch (DomainException $e) {
            $this->renderPrepView($user, $team, $match, ['preparation' => $e->getMessage()]);
            return;
        }

        Response::redirect('/matches/' . $match['id']);
    }

    private function renderPrepView(array $user, array $team, array $match, array $errors): void
    {
        $seasonId   = (int) ($team['season_id'] ?? 0);
        $players    = $seasonId > 0
            ? $this->players->findActiveByTeamAndSeason((int) $team['id'], $seasonId)
            : [];
        $selections  = $this->selections->findByMatch((int) $match['id']);
        $lineupSlots = $this->lineup->findByMatch((int) $match['id']);
        $formations  = $this->teams->findFormations((int) $team['id']);

        echo View::layout('matches/prepare', [
            'match'       => $match,
            'team'        => $team,
            'players'     => $players,
            'selections'  => $selections,
            'lineupSlots' => $lineupSlots,
            'formations'  => $formations,
            'errors'      => $errors,
            'user'        => $user,
        ]);
    }
}

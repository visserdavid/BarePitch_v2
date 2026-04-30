<?php

declare(strict_types=1);

namespace BarePitch\Services;

use BarePitch\Core\Exceptions\AuthorizationException;
use BarePitch\Core\Session;
use BarePitch\Repositories\TeamRepository;

class TeamContextService
{
    public function __construct(private readonly TeamRepository $teams) {}

    public function getActiveTeam(array $user): ?array
    {
        $teamId = Session::get('active_team_id');
        if (!$teamId) {
            return null;
        }

        $team = $this->teams->findById((int) $teamId);
        if (!$team) {
            Session::remove('active_team_id');
            return null;
        }

        // Verify user still has access
        $isAdmin = (int) ($user['is_administrator'] ?? 0) === 1;
        $accessible = $this->teams->findAccessibleForUser((int) $user['id'], $isAdmin);
        $teamIds = array_column($accessible, 'id');

        if (!in_array($team['id'], $teamIds, false)) {
            Session::remove('active_team_id');
            return null;
        }

        return $team;
    }

    public function setActiveTeam(int $teamId, array $user): void
    {
        $team = $this->teams->findById($teamId);
        if (!$team) {
            throw new \InvalidArgumentException("Team $teamId not found.");
        }

        $isAdmin = (int) ($user['is_administrator'] ?? 0) === 1;
        $accessible = $this->teams->findAccessibleForUser((int) $user['id'], $isAdmin);
        $teamIds = array_column($accessible, 'id');

        if (!in_array($teamId, $teamIds, false)) {
            throw new AuthorizationException('You do not have access to this team.');
        }

        Session::set('active_team_id', $teamId);
    }

    public function requireTeamContext(array $user): array
    {
        $team = $this->getActiveTeam($user);
        if ($team === null) {
            throw new AuthorizationException('No active team context. Please select a team.');
        }
        return $team;
    }
}

<?php

declare(strict_types=1);

namespace BarePitch\Policies;

use BarePitch\Core\Exceptions\AuthorizationException;

class TeamPolicy
{
    use PolicyHelpers;

    /**
     * Any authenticated user with a role on the team may view it.
     * Administrators can view any team.
     */
    public static function canView(array $user, array $team): void
    {
        if (!self::hasAccessToTeam($user, (int) $team['id'])) {
            throw new AuthorizationException('You do not have access to this team.');
        }
    }

    /**
     * Requires admin OR coach / team_manager role on the team.
     */
    public static function canManage(array $user, array $team): void
    {
        if (self::isAdmin($user)) {
            return;
        }

        if (!self::hasRoleForTeam($user, (int) $team['id'], ['coach', 'team_manager'])) {
            throw new AuthorizationException('You do not have permission to manage this team.');
        }
    }
}

<?php

declare(strict_types=1);

namespace BarePitch\Policies;

use BarePitch\Core\Exceptions\AuthorizationException;

class PlayerPolicy
{
    use PolicyHelpers;

    /**
     * Any user with team access may view the team's players.
     */
    public static function canView(array $user, array $team): void
    {
        if (!self::hasAccessToTeam($user, (int) $team['id'])) {
            throw new AuthorizationException("You do not have access to this team's players.");
        }
    }

    /**
     * Only administrators or team_managers may create players.
     */
    public static function canCreate(array $user, array $team): void
    {
        if (self::isAdmin($user)) {
            return;
        }

        if (!self::hasRoleForTeam($user, (int) $team['id'], ['team_manager'])) {
            throw new AuthorizationException(
                'Only administrators or team managers can create players.'
            );
        }
    }
}

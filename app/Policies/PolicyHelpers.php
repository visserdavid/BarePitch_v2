<?php

declare(strict_types=1);

namespace BarePitch\Policies;

/**
 * Shared authorization helpers for all Policy classes.
 *
 * Policies receive a pre-loaded $user array with 'roles' already attached
 * by AuthService. No database access is performed here.
 */
trait PolicyHelpers
{
    /**
     * Returns true when the user has the site-wide administrator flag set.
     * is_administrator is stored as a TINYINT (1 = true) on the user table,
     * NOT as a role in user_team_role.
     */
    protected static function isAdmin(array $user): bool
    {
        return (int) ($user['is_administrator'] ?? 0) === 1;
    }

    /**
     * Returns true when the user holds at least one of the $allowedRoles
     * for the given $teamId.
     */
    protected static function hasRoleForTeam(array $user, int $teamId, array $allowedRoles): bool
    {
        foreach ($user['roles'] ?? [] as $role) {
            if (
                (int) $role['team_id'] === $teamId
                && in_array($role['role_key'], $allowedRoles, true)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns true when the user is an admin OR holds one of coach / trainer /
     * team_manager for the given team — i.e. any legitimate team-level access.
     */
    protected static function hasAccessToTeam(array $user, int $teamId): bool
    {
        if (self::isAdmin($user)) {
            return true;
        }

        return self::hasRoleForTeam($user, $teamId, ['coach', 'trainer', 'team_manager']);
    }
}

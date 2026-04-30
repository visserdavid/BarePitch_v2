<?php

declare(strict_types=1);

namespace BarePitch\Policies;

use BarePitch\Core\Exceptions\AuthorizationException;
use BarePitch\Domain\MatchStatus;

class MatchPolicy
{
    use PolicyHelpers;

    /**
     * Any user with team access may view matches.
     */
    public static function canView(array $user, array $match): void
    {
        if (!self::hasAccessToTeam($user, (int) $match['team_id'])) {
            throw new AuthorizationException('You do not have access to this match.');
        }
    }

    /**
     * Only coaches (or administrators) may create matches.
     */
    public static function canCreate(array $user, array $team): void
    {
        if (self::isAdmin($user)) {
            return;
        }

        if (!self::hasRoleForTeam($user, (int) $team['id'], ['coach'])) {
            throw new AuthorizationException('Only coaches or administrators can create matches.');
        }
    }

    /**
     * Edit is allowed for coaches/admins on non-finished matches only.
     */
    public static function canEdit(array $user, array $match): void
    {
        // Reuse canCreate with a synthetic team array — only needs ['id']
        self::canCreate($user, ['id' => $match['team_id']]);

        if ($match['status'] === MatchStatus::Finished->value) {
            throw new AuthorizationException('Finished matches cannot be edited.');
        }
    }

    /**
     * Preparation is available when the match is planned or already prepared.
     */
    public static function canPrepare(array $user, array $match): void
    {
        self::requireCoachOrAdmin($user, (int) $match['team_id']);

        if (!in_array($match['status'], [
            MatchStatus::Planned->value,
            MatchStatus::Prepared->value,
        ], true)) {
            throw new AuthorizationException(
                'Match preparation is only available for planned or prepared matches.'
            );
        }
    }

    /**
     * A match can only be started when it is in the 'prepared' state.
     * A planned match cannot be started directly.
     */
    public static function canStart(array $user, array $match): void
    {
        self::requireCoachOrAdmin($user, (int) $match['team_id']);

        if ($match['status'] !== MatchStatus::Prepared->value) {
            throw new AuthorizationException('Only a prepared match can be started.');
        }
    }

    /**
     * Live events may only be registered while the match is active.
     */
    public static function canRegisterLiveEvent(array $user, array $match): void
    {
        self::requireCoachOrAdmin($user, (int) $match['team_id']);

        if ($match['status'] !== MatchStatus::Active->value) {
            throw new AuthorizationException(
                'Live events can only be registered for an active match.'
            );
        }
    }

    /**
     * A match can only be finished when it is active.
     */
    public static function canFinish(array $user, array $match): void
    {
        self::requireCoachOrAdmin($user, (int) $match['team_id']);

        if ($match['status'] !== MatchStatus::Active->value) {
            throw new AuthorizationException('Only an active match can be finished.');
        }
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    /**
     * Throws unless the user is an administrator or holds the coach role
     * on the given team.
     */
    private static function requireCoachOrAdmin(array $user, int $teamId): void
    {
        if (self::isAdmin($user)) {
            return;
        }

        if (!self::hasRoleForTeam($user, $teamId, ['coach'])) {
            throw new AuthorizationException('You do not have permission to perform this action.');
        }
    }
}

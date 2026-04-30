<?php

declare(strict_types=1);

namespace BarePitch\Tests\Feature\Authorization;

use BarePitch\Core\Exceptions\AuthorizationException;
use BarePitch\Policies\MatchPolicy;
use PHPUnit\Framework\TestCase;

/**
 * Tests MatchPolicy authorization rules.
 *
 * These tests exercise the policy layer directly, which is the correct
 * isolation boundary for authorization checks. The policy methods are
 * static and accept pre-loaded user/match arrays — no HTTP layer needed.
 *
 * User arrays use the same shape that AuthService::getCurrentUser() returns:
 *   ['id', 'is_administrator', 'roles' => [['team_id', 'role_key'], ...]]
 *
 * No DB required — extends plain TestCase. Team IDs are fixed constants.
 */
class MatchAuthTest extends TestCase
{
    // Fixed IDs for in-memory policy tests — no DB required
    private const TEAM_ID    = 1;
    private const COACH_ID   = 10;
    private const TRAINER_ID = 11;

    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
    }

    // ----------------------------------------------------------------
    // canStart: planned match cannot be started
    // ----------------------------------------------------------------

    public function testCoachCannotStartPlannedMatch(): void
    {
        $user  = $this->buildUser(self::COACH_ID, 'coach');
        $match = $this->buildMatch('planned');

        $this->expectException(AuthorizationException::class);
        MatchPolicy::canStart($user, $match);
    }

    public function testTrainerCannotStartPreparedMatch(): void
    {
        $user  = $this->buildUser(self::TRAINER_ID, 'trainer');
        $match = $this->buildMatch('prepared');

        $this->expectException(AuthorizationException::class);
        MatchPolicy::canStart($user, $match);
    }

    // ----------------------------------------------------------------
    // canStart: prepared match can be started by coach
    // ----------------------------------------------------------------

    public function testCoachCanStartPreparedMatch(): void
    {
        $user  = $this->buildUser(self::COACH_ID, 'coach');
        $match = $this->buildMatch('prepared');

        // Must not throw
        MatchPolicy::canStart($user, $match);
        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // Unauthenticated access (no user in session)
    // ----------------------------------------------------------------

    public function testUnauthenticatedCannotStartMatch(): void
    {
        // Unauthenticated = no user_id in session
        $this->assertArrayNotHasKey('user_id', $_SESSION);

        // The policy rejects a user with no team roles
        $anonymousUser = ['id' => 0, 'is_administrator' => 0, 'roles' => []];
        $match         = $this->buildMatch('prepared');

        $this->expectException(AuthorizationException::class);
        MatchPolicy::canStart($anonymousUser, $match);
    }

    // ----------------------------------------------------------------
    // canFinish
    // ----------------------------------------------------------------

    public function testCoachCanFinishActiveMatch(): void
    {
        $user  = $this->buildUser(self::COACH_ID, 'coach');
        $match = $this->buildMatch('active');

        MatchPolicy::canFinish($user, $match);
        $this->assertTrue(true);
    }

    public function testTrainerCannotFinishActiveMatch(): void
    {
        $user  = $this->buildUser(self::TRAINER_ID, 'trainer');
        $match = $this->buildMatch('active');

        $this->expectException(AuthorizationException::class);
        MatchPolicy::canFinish($user, $match);
    }

    public function testCoachCannotFinishNonActiveMatch(): void
    {
        $user  = $this->buildUser(self::COACH_ID, 'coach');
        $match = $this->buildMatch('finished');

        $this->expectException(AuthorizationException::class);
        MatchPolicy::canFinish($user, $match);
    }

    // ----------------------------------------------------------------
    // canPrepare
    // ----------------------------------------------------------------

    public function testCoachCanPreparePlannedMatch(): void
    {
        $user  = $this->buildUser(self::COACH_ID, 'coach');
        $match = $this->buildMatch('planned');

        MatchPolicy::canPrepare($user, $match);
        $this->assertTrue(true);
    }

    public function testTrainerCannotPrepareMatch(): void
    {
        $user  = $this->buildUser(self::TRAINER_ID, 'trainer');
        $match = $this->buildMatch('planned');

        $this->expectException(AuthorizationException::class);
        MatchPolicy::canPrepare($user, $match);
    }

    public function testCoachCanPreparePreparedMatch(): void
    {
        $user  = $this->buildUser(self::COACH_ID, 'coach');
        $match = $this->buildMatch('prepared');

        // Must not throw — a coach may re-prepare an already-prepared match
        MatchPolicy::canPrepare($user, $match);
        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    private function buildUser(int $userId, string $roleKey): array
    {
        return [
            'id'               => $userId,
            'is_administrator' => 0,
            'roles'            => [
                ['team_id' => self::TEAM_ID, 'role_key' => $roleKey],
            ],
        ];
    }

    private function buildMatch(string $status): array
    {
        return [
            'id'      => 1,
            'team_id' => self::TEAM_ID,
            'status'  => $status,
        ];
    }
}

<?php

declare(strict_types=1);

namespace BarePitch\Tests\Feature\Auth;

use BarePitch\Core\Exceptions\CsrfException;
use BarePitch\Core\Request;
use BarePitch\Core\Session;
use BarePitch\Http\Controllers\AuthController;
use BarePitch\Repositories\UserRepository;
use BarePitch\Services\AuthService;
use BarePitch\Tests\Feature\FeatureTestCase;

/**
 * Tests the dev-login controller behaviour.
 *
 * Because Response::abort() and Response::redirect() call exit(), we
 * test at the service layer for the "disabled" case, and exercise the
 * controller's guard branch through the service's own guard.
 *
 * For the "enabled and works" case we call AuthService::loginAs() directly,
 * which is what the controller delegates to, and assert the session state.
 */
class DevLoginTest extends FeatureTestCase
{
    // ----------------------------------------------------------------
    // AuthController::devLoginForm — disabled
    // ----------------------------------------------------------------

    /**
     * When ENABLE_DEV_LOGIN is not 'true', AuthController calls Response::abort(404).
     * Response::abort() calls exit() which we can't catch without a separate process.
     * We test the guard behaviour through the service instead, which throws RuntimeException.
     */
    public function testDevLoginServiceThrowsWhenDisabled(): void
    {
        putenv('ENABLE_DEV_LOGIN=false');
        putenv('APP_ENV=local');

        $pdo        = static::$db;
        $userRepo   = new UserRepository($pdo);
        $authService = new AuthService($userRepo);

        $this->expectException(\RuntimeException::class);
        $authService->loginAs(static::$coachId);
    }

    public function testDevLoginServiceThrowsWhenAppEnvIsNotLocal(): void
    {
        putenv('ENABLE_DEV_LOGIN=true');
        putenv('APP_ENV=production');

        $pdo        = static::$db;
        $userRepo   = new UserRepository($pdo);
        $authService = new AuthService($userRepo);

        $this->expectException(\RuntimeException::class);
        $authService->loginAs(static::$coachId);
    }

    // ----------------------------------------------------------------
    // AuthService::loginAs — enabled, valid user
    // ----------------------------------------------------------------

    public function testLoginAsSucceedsWhenEnabled(): void
    {
        putenv('ENABLE_DEV_LOGIN=true');
        putenv('APP_ENV=local');

        $pdo         = static::$db;
        $userRepo    = new UserRepository($pdo);
        $authService = new AuthService($userRepo);

        // Simulate what Session::regenerate() does in a no-session environment:
        // It won't throw in CLI but won't change the superglobal either.
        // We assert the session user_id is set after loginAs().
        $authService->loginAs(static::$coachId);

        $this->assertSame(static::$coachId, $_SESSION['user_id']);
    }

    public function testLoginAsFailsForNonExistentUser(): void
    {
        putenv('ENABLE_DEV_LOGIN=true');
        putenv('APP_ENV=local');

        $pdo         = static::$db;
        $userRepo    = new UserRepository($pdo);
        $authService = new AuthService($userRepo);

        $this->expectException(\InvalidArgumentException::class);
        $authService->loginAs(999999);
    }

    // ----------------------------------------------------------------
    // CSRF enforcement on devLogin POST
    // ----------------------------------------------------------------

    public function testDevLoginPostWithoutCsrfTokenThrowsCsrfException(): void
    {
        putenv('ENABLE_DEV_LOGIN=true');
        putenv('APP_ENV=local');

        $pdo         = static::$db;
        $userRepo    = new UserRepository($pdo);
        $authService = new AuthService($userRepo);
        $controller  = new AuthController($authService, $userRepo);

        // Ensure no CSRF token in session
        $_SESSION = [];
        $_POST    = ['user_id' => (string) static::$coachId]; // no _csrf field

        $request = new Request();

        $this->expectException(CsrfException::class);
        $controller->devLogin($request);
    }

    protected function tearDown(): void
    {
        // Restore env var to test-default
        putenv('ENABLE_DEV_LOGIN=true');
        putenv('APP_ENV=testing');
        $_POST    = [];
        $_SESSION = [];
        parent::tearDown();
    }
}

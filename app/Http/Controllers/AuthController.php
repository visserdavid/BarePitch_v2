<?php

declare(strict_types=1);

namespace BarePitch\Http\Controllers;

use BarePitch\Core\Csrf;
use BarePitch\Core\Request;
use BarePitch\Core\Response;
use BarePitch\Core\View;
use BarePitch\Core\Exceptions\ValidationException;
use BarePitch\Repositories\UserRepository;
use BarePitch\Services\AuthService;

class AuthController
{
    public function __construct(
        private readonly AuthService    $auth,
        private readonly UserRepository $users,
    ) {}

    public function devLoginForm(Request $request, array $params = []): void
    {
        if (getenv('ENABLE_DEV_LOGIN') !== 'true') {
            Response::abort(404);
        }

        $users = $this->users->findActive();

        echo View::layout('auth/dev-login', [
            'users'  => $users,
            'errors' => [],
        ]);
    }

    public function devLogin(Request $request, array $params = []): void
    {
        if (getenv('ENABLE_DEV_LOGIN') !== 'true') {
            Response::abort(404);
        }

        Csrf::verify($request);

        $userIdRaw = $request->post('user_id');
        $userId    = ($userIdRaw !== null && $userIdRaw !== '') ? (int) $userIdRaw : null;

        if ($userId === null || $userId < 1) {
            $users = $this->users->findActive();
            echo View::layout('auth/dev-login', [
                'users'  => $users,
                'errors' => ['user_id' => 'Please select a user.'],
            ]);
            return;
        }

        try {
            $this->auth->loginAs($userId);
        } catch (\InvalidArgumentException $e) {
            $users = $this->users->findActive();
            echo View::layout('auth/dev-login', [
                'users'  => $users,
                'errors' => ['user_id' => $e->getMessage()],
            ]);
            return;
        }

        Response::redirect('/');
    }

    public function logout(Request $request, array $params = []): void
    {
        Csrf::verify($request);
        $this->auth->logout();
        Response::redirect('/');
    }
}

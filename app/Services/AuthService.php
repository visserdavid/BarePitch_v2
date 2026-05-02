<?php

declare(strict_types=1);

namespace BarePitch\Services;

use BarePitch\Core\Exceptions\AuthorizationException;
use BarePitch\Core\Session;
use BarePitch\Repositories\UserRepository;

class AuthService
{
    public function __construct(private readonly UserRepository $users) {}

    public function getCurrentUser(): ?array
    {
        $userId = Session::get('user_id');
        if (!$userId) {
            return null;
        }

        $user = $this->users->findById((int) $userId);
        if (!$user || !(int) $user['is_active']) {
            return null;
        }

        // Attach roles for policy checks
        $user['roles'] = $this->users->getTeamRoles((int) $user['id']);
        return $user;
    }

    public function requireAuth(): array
    {
        $user = $this->getCurrentUser();
        if ($user === null) {
            throw new AuthorizationException('You must be logged in to perform this action.');
        }
        return $user;
    }

    /**
     * Dev-only login. The guard is enforced here so callers cannot bypass it.
     */
    public function loginAs(int $userId): void
    {
        if (!$this->devLoginEnabled()) {
            throw new \RuntimeException('Dev login is disabled');
        }

        $user = $this->users->findById($userId);
        if (!$user || !(int) $user['is_active']) {
            throw new \InvalidArgumentException("User $userId not found or inactive.");
        }
        Session::regenerate();
        Session::set('user_id', $userId);
    }

    public function devLoginEnabled(): bool
    {
        return getenv('ENABLE_DEV_LOGIN') === 'true' && getenv('APP_ENV') === 'local';
    }

    public function logout(): void
    {
        Session::destroy();
    }
}

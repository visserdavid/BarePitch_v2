<?php

declare(strict_types=1);

namespace BarePitch\Core;

class App
{
    public static function boot(): void
    {
        if (getenv('APP_DEBUG') === 'true') {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(0);
            ini_set('display_errors', '0');
        }

        date_default_timezone_set('UTC');

        $idleMinutes = (int) (getenv('SESSION_IDLE_MINUTES') ?: 30);

        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'gc_maxlifetime'  => $idleMinutes * 60,
        ]);

        Database::connect();
    }
}

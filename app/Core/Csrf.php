<?php

declare(strict_types=1);

namespace BarePitch\Core;

use BarePitch\Core\Exceptions\CsrfException;

class Csrf
{
    public static function field(): string
    {
        $token = htmlspecialchars(Session::csrfToken(), ENT_QUOTES, 'UTF-8');

        return '<input type="hidden" name="_csrf" value="' . $token . '">';
    }

    public static function verify(Request $request): void
    {
        $token = (string) $request->post('_csrf', '');

        if (!Session::verifyCsrf($token)) {
            throw new CsrfException();
        }
    }
}

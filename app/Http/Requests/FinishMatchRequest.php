<?php

declare(strict_types=1);

namespace BarePitch\Http\Requests;

use BarePitch\Core\Request;
use BarePitch\Core\Exceptions\ValidationException;

class FinishMatchRequest
{
    public static function validate(Request $request): array
    {
        $confirm = (string) ($request->input('confirm') ?? '');

        if ($confirm !== '1') {
            throw new ValidationException(['confirm' => 'confirm must equal "1".']);
        }

        return ['confirm' => '1'];
    }
}

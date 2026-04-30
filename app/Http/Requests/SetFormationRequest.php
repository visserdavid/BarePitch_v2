<?php

declare(strict_types=1);

namespace BarePitch\Http\Requests;

use BarePitch\Core\Request;
use BarePitch\Core\Exceptions\ValidationException;

class SetFormationRequest
{
    public static function validate(Request $request): array
    {
        $errors = [];

        $raw         = $request->input('formation_id');
        $formationId = ($raw !== null && $raw !== '') ? (int) $raw : null;

        if ($formationId === null || $formationId < 1) {
            $errors['formation_id'] = 'formation_id is required and must be a positive integer.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return ['formation_id' => (int) $formationId];
    }
}

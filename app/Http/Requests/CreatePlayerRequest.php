<?php

declare(strict_types=1);

namespace BarePitch\Http\Requests;

use BarePitch\Core\Request;
use BarePitch\Core\Exceptions\ValidationException;
use BarePitch\Domain\PositionLine;

class CreatePlayerRequest
{
    private const FIRST_NAME_MAX    = 80;
    private const LAST_NAME_MAX     = 80;
    private const DISPLAY_NAME_MAX  = 120;
    private const SHIRT_NUMBER_MIN  = 1;
    private const SHIRT_NUMBER_MAX  = 99;

    public static function validate(Request $request): array
    {
        $errors = [];

        // first_name (required, max 80)
        $firstName = trim((string) ($request->post('first_name') ?? ''));
        if ($firstName === '') {
            $errors['first_name'] = 'first_name is required.';
        } elseif (mb_strlen($firstName) > self::FIRST_NAME_MAX) {
            $errors['first_name'] = 'first_name must not exceed ' . self::FIRST_NAME_MAX . ' characters.';
        }

        // last_name (required, max 80)
        $lastName = trim((string) ($request->post('last_name') ?? ''));
        if ($lastName === '') {
            $errors['last_name'] = 'last_name is required.';
        } elseif (mb_strlen($lastName) > self::LAST_NAME_MAX) {
            $errors['last_name'] = 'last_name must not exceed ' . self::LAST_NAME_MAX . ' characters.';
        }

        // display_name (optional, max 120)
        $displayNameRaw = trim((string) ($request->post('display_name') ?? ''));
        $displayName    = $displayNameRaw !== '' ? $displayNameRaw : null;
        if ($displayName !== null && mb_strlen($displayName) > self::DISPLAY_NAME_MAX) {
            $errors['display_name'] = 'display_name must not exceed ' . self::DISPLAY_NAME_MAX . ' characters.';
        }

        // shirt_number (required, 1–99 int)
        $shirtNumberRaw = $request->post('shirt_number');
        $shirtNumber    = ($shirtNumberRaw !== null && $shirtNumberRaw !== '') ? (int) $shirtNumberRaw : null;
        if ($shirtNumber === null) {
            $errors['shirt_number'] = 'shirt_number is required.';
        } elseif ($shirtNumber < self::SHIRT_NUMBER_MIN || $shirtNumber > self::SHIRT_NUMBER_MAX) {
            $errors['shirt_number'] = 'shirt_number must be between ' . self::SHIRT_NUMBER_MIN . ' and ' . self::SHIRT_NUMBER_MAX . '.';
        }

        // position_line (required, PositionLine enum: GK|DEF|MID|FWD)
        $positionLineRaw = (string) ($request->post('position_line') ?? '');
        $validLines      = array_column(PositionLine::cases(), 'value');
        if ($positionLineRaw === '') {
            $errors['position_line'] = 'position_line is required.';
        } elseif (!in_array($positionLineRaw, $validLines, true)) {
            $errors['position_line'] = 'position_line must be one of: ' . implode(', ', $validLines) . '.';
        }

        // date_of_birth (optional, Y-m-d)
        $dobRaw      = trim((string) ($request->post('date_of_birth') ?? ''));
        $dateOfBirth = null;
        if ($dobRaw !== '') {
            $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $dobRaw);
            if ($parsed === false || $parsed->format('Y-m-d') !== $dobRaw) {
                $errors['date_of_birth'] = 'date_of_birth must be in Y-m-d format.';
            } else {
                $dateOfBirth = $dobRaw;
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'first_name'    => $firstName,
            'last_name'     => $lastName,
            'display_name'  => $displayName,
            'shirt_number'  => (int) $shirtNumber,
            'position_line' => $positionLineRaw,
            'date_of_birth' => $dateOfBirth,
        ];
    }
}

<?php

declare(strict_types=1);

namespace BarePitch\Http\Requests;

use BarePitch\Core\Request;
use BarePitch\Core\Exceptions\ValidationException;

class CreateMatchRequest
{
    private const HOME_AWAY_VALUES  = ['home', 'away', 'neutral'];
    private const MATCH_TYPE_VALUES = ['league', 'cup', 'friendly'];
    private const OPPONENT_MAX_LEN  = 120;

    public static function validate(Request $request): array
    {
        $errors = [];

        // phase_id
        $phaseIdRaw = $request->input('phase_id');
        $phaseId    = ($phaseIdRaw !== null && $phaseIdRaw !== '') ? (int) $phaseIdRaw : null;
        if ($phaseId === null || $phaseId < 1) {
            $errors['phase_id'] = 'phase_id is required and must be a positive integer.';
        }

        // date (Y-m-d)
        $date = trim((string) ($request->input('date') ?? ''));
        if ($date === '') {
            $errors['date'] = 'date is required.';
        } elseif (!\DateTimeImmutable::createFromFormat('Y-m-d', $date)
            || \DateTimeImmutable::createFromFormat('Y-m-d', $date)->format('Y-m-d') !== $date
        ) {
            $errors['date'] = 'date must be in Y-m-d format.';
        }

        // kick_off_time (H:i, optional)
        $kickOffTimeRaw = $request->input('kick_off_time');
        $kickOffTime    = null;
        if ($kickOffTimeRaw !== null && trim((string) $kickOffTimeRaw) !== '') {
            $kickOffTime = trim((string) $kickOffTimeRaw);
            if (!\DateTimeImmutable::createFromFormat('H:i', $kickOffTime)
                || \DateTimeImmutable::createFromFormat('H:i', $kickOffTime)->format('H:i') !== $kickOffTime
            ) {
                $errors['kick_off_time'] = 'kick_off_time must be in H:i format.';
                $kickOffTime = null;
            }
        }

        // opponent_name
        $opponentName = trim((string) ($request->input('opponent_name') ?? ''));
        if ($opponentName === '') {
            $errors['opponent_name'] = 'opponent_name is required.';
        } elseif (mb_strlen($opponentName) > self::OPPONENT_MAX_LEN) {
            $errors['opponent_name'] = 'opponent_name must not exceed ' . self::OPPONENT_MAX_LEN . ' characters.';
        }

        // home_away
        $homeAway = (string) ($request->input('home_away') ?? '');
        if ($homeAway === '') {
            $errors['home_away'] = 'home_away is required.';
        } elseif (!in_array($homeAway, self::HOME_AWAY_VALUES, true)) {
            $errors['home_away'] = 'home_away must be one of: ' . implode(', ', self::HOME_AWAY_VALUES) . '.';
        }

        // match_type
        $matchType = (string) ($request->input('match_type') ?? '');
        if ($matchType === '') {
            $errors['match_type'] = 'match_type is required.';
        } elseif (!in_array($matchType, self::MATCH_TYPE_VALUES, true)) {
            $errors['match_type'] = 'match_type must be one of: ' . implode(', ', self::MATCH_TYPE_VALUES) . '.';
        }

        // regular_half_duration_minutes (1–90)
        $durationRaw = $request->input('regular_half_duration_minutes');
        $duration    = ($durationRaw !== null && $durationRaw !== '') ? (int) $durationRaw : null;
        if ($duration === null) {
            $errors['regular_half_duration_minutes'] = 'regular_half_duration_minutes is required.';
        } elseif ($duration < 1 || $duration > 90) {
            $errors['regular_half_duration_minutes'] = 'regular_half_duration_minutes must be between 1 and 90.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'phase_id'                       => (int) $phaseId,
            'date'                           => $date,
            'kick_off_time'                  => $kickOffTime,
            'opponent_name'                  => $opponentName,
            'home_away'                      => $homeAway,
            'match_type'                     => $matchType,
            'regular_half_duration_minutes'  => (int) $duration,
        ];
    }
}

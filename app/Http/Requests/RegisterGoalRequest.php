<?php

declare(strict_types=1);

namespace BarePitch\Http\Requests;

use BarePitch\Core\Request;
use BarePitch\Core\Exceptions\ValidationException;
use BarePitch\Domain\TeamSide;
use BarePitch\Domain\GoalZone;

class RegisterGoalRequest
{
    public static function validate(Request $request): array
    {
        $errors = [];

        // team_side: required, must be a valid TeamSide value
        $validSides = array_map(fn(TeamSide $s) => $s->value, TeamSide::cases());
        $teamSide   = (string) ($request->input('team_side') ?? '');
        if ($teamSide === '') {
            $errors['team_side'] = 'team_side is required.';
        } elseif (!in_array($teamSide, $validSides, true)) {
            $errors['team_side'] = 'team_side must be one of: ' . implode(', ', $validSides) . '.';
        }

        // player_selection_id: required when team_side is 'own'
        $playerSelectionIdRaw = $request->input('player_selection_id');
        $playerSelectionId    = null;
        $isOwnGoal            = $teamSide === TeamSide::Own->value;

        if ($isOwnGoal) {
            if ($playerSelectionIdRaw === null || $playerSelectionIdRaw === '') {
                $errors['player_selection_id'] = 'player_selection_id is required for own-team goals.';
            } else {
                $playerSelectionId = (int) $playerSelectionIdRaw;
                if ($playerSelectionId < 1) {
                    $errors['player_selection_id'] = 'player_selection_id must be a positive integer.';
                    $playerSelectionId = null;
                }
            }
        } elseif ($playerSelectionIdRaw !== null && $playerSelectionIdRaw !== '') {
            $playerSelectionId = (int) $playerSelectionIdRaw;
            if ($playerSelectionId < 1) {
                $errors['player_selection_id'] = 'player_selection_id must be a positive integer.';
                $playerSelectionId = null;
            }
        }

        // zone_code: optional, must be a valid GoalZone code when provided
        $zoneCodeRaw  = $request->input('zone_code');
        $zoneCode     = null;
        if ($zoneCodeRaw !== null && trim((string) $zoneCodeRaw) !== '') {
            $zoneCode = trim((string) $zoneCodeRaw);
            if (!in_array($zoneCode, GoalZone::validCodes(), true)) {
                $errors['zone_code'] = 'zone_code must be one of: ' . implode(', ', GoalZone::validCodes()) . '.';
                $zoneCode = null;
            }
        }

        // assist_selection_id: optional positive int, must not equal player_selection_id
        $assistSelectionIdRaw = $request->input('assist_selection_id');
        $assistSelectionId    = null;
        if ($assistSelectionIdRaw !== null && $assistSelectionIdRaw !== '') {
            $assistSelectionId = (int) $assistSelectionIdRaw;
            if ($assistSelectionId < 1) {
                $errors['assist_selection_id'] = 'assist_selection_id must be a positive integer.';
                $assistSelectionId = null;
            } elseif ($playerSelectionId !== null && $assistSelectionId === $playerSelectionId) {
                $errors['assist_selection_id'] = 'assist_selection_id must not equal player_selection_id.';
                $assistSelectionId = null;
            }
        }

        // minute_display: required, 0–120
        $minuteRaw    = $request->input('minute_display');
        $minuteDisplay = null;
        if ($minuteRaw === null || $minuteRaw === '') {
            $errors['minute_display'] = 'minute_display is required.';
        } else {
            $minuteDisplay = (int) $minuteRaw;
            if ($minuteDisplay < 0 || $minuteDisplay > 120) {
                $errors['minute_display'] = 'minute_display must be between 0 and 120.';
                $minuteDisplay = null;
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'team_side'            => $teamSide,
            'player_selection_id'  => $playerSelectionId,
            'assist_selection_id'  => $assistSelectionId,
            'zone_code'            => $zoneCode,
            'minute_display'       => (int) $minuteDisplay,
        ];
    }
}

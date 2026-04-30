<?php

declare(strict_types=1);

namespace BarePitch\Http\Requests;

use BarePitch\Core\Request;
use BarePitch\Core\Exceptions\ValidationException;
use BarePitch\Domain\AttendanceStatus;

class SaveAttendanceRequest
{
    public static function validate(Request $request): array
    {
        $errors = [];

        $validStatuses = array_map(fn(AttendanceStatus $s) => $s->value, AttendanceStatus::cases());

        // attendance must be an array keyed by player_id with a status value
        $raw = $request->input('attendance');

        if (!is_array($raw) || $raw === []) {
            throw new ValidationException(['attendance' => 'attendance must be a non-empty array.']);
        }

        $normalized = [];
        foreach ($raw as $playerId => $entry) {
            $playerIdInt = (int) $playerId;
            if ($playerIdInt < 1) {
                $errors["attendance.{$playerId}"] = "Player ID {$playerId} is invalid.";
                continue;
            }

            // Entry may be an array with a 'status' key or a plain string
            if (is_array($entry)) {
                $status = $entry['status'] ?? null;
            } else {
                $status = $entry;
            }

            $status = (string) ($status ?? '');

            if (!in_array($status, $validStatuses, true)) {
                $errors["attendance.{$playerId}.status"] =
                    "Status for player {$playerId} must be one of: " . implode(', ', $validStatuses) . '.';
                continue;
            }

            $normalized[$playerIdInt] = $status;
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return ['attendance' => $normalized];
    }
}

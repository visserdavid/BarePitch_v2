<?php

declare(strict_types=1);

namespace BarePitch\Domain;

final class PreparationChecker
{
    /**
     * @param array $match  The match row (has 'status', 'team_id')
     * @param array $selections  Rows from match_selection (each has 'attendance_status', 'is_starting')
     * @param array $lineupSlots  Rows from match_lineup_slot (each has 'formation_position_id', is null for bench)
     * @param array $formationPositions  Rows from formation_position for the chosen formation
     * @return string[]  Empty array if valid; error messages if not
     */
    public static function check(
        array $match,
        array $selections,
        array $lineupSlots,
        array $formationPositions
    ): array {
        $errors = [];

        // Count present players
        $presentCount = count(array_filter(
            $selections,
            fn($s) => $s['attendance_status'] === AttendanceStatus::Present->value
        ));

        if ($presentCount < 11) {
            $errors[] = 'At least 11 players must be marked as present.';
        }

        // Formation required
        if (empty($formationPositions)) {
            $errors[] = 'A formation must be selected before confirming preparation.';
            // Can't check lineup completeness without formation positions
            return $errors;
        }

        // All formation positions must be filled
        $filledPositionIds = array_filter(
            array_column($lineupSlots, 'formation_position_id')
        );

        $requiredPositionIds = array_column($formationPositions, 'id');

        $missingPositions = array_diff($requiredPositionIds, $filledPositionIds);
        if (!empty($missingPositions)) {
            $errors[] = 'All formation positions must be filled in the lineup.';
        }

        return $errors;
    }
}

<?php

declare(strict_types=1);

namespace BarePitch\Http\Requests;

use BarePitch\Core\Request;
use BarePitch\Core\Exceptions\ValidationException;

class SaveLineupRequest
{
    public static function validate(Request $request): array
    {
        $errors = [];

        $raw = $request->input('slots');

        if (!is_array($raw)) {
            throw new ValidationException(['slots' => 'slots must be an array.']);
        }

        $seenSelectionIds        = [];
        $seenFormationPositionIds = [];
        $slots                   = [];

        foreach ($raw as $index => $slot) {
            if (!is_array($slot)) {
                $errors["slots.{$index}"] = "Each slot must be an object.";
                continue;
            }

            // selection_id: required positive int
            $selectionIdRaw = $slot['selection_id'] ?? null;
            $selectionId    = ($selectionIdRaw !== null && $selectionIdRaw !== '') ? (int) $selectionIdRaw : null;

            if ($selectionId === null || $selectionId < 1) {
                $errors["slots.{$index}.selection_id"] = "selection_id is required and must be a positive integer.";
            } elseif (isset($seenSelectionIds[$selectionId])) {
                $errors["slots.{$index}.selection_id"] = "Duplicate selection_id {$selectionId}.";
            } else {
                $seenSelectionIds[$selectionId] = true;
            }

            // formation_position_id: optional positive int, but no duplicates among non-null values
            $posIdRaw = $slot['formation_position_id'] ?? null;
            $posId    = null;
            if ($posIdRaw !== null && $posIdRaw !== '') {
                $posId = (int) $posIdRaw;
                if ($posId < 1) {
                    $errors["slots.{$index}.formation_position_id"] =
                        "formation_position_id must be a positive integer when provided.";
                    $posId = null;
                } elseif (isset($seenFormationPositionIds[$posId])) {
                    $errors["slots.{$index}.formation_position_id"] =
                        "Duplicate formation_position_id {$posId}.";
                    $posId = null;
                } else {
                    $seenFormationPositionIds[$posId] = true;
                }
            }

            if (!isset($errors["slots.{$index}.selection_id"])) {
                $slots[] = [
                    'match_selection_id'     => (int) $selectionId,
                    'formation_position_id'  => $posId,
                ];
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return ['slots' => $slots];
    }
}

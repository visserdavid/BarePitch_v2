<?php

declare(strict_types=1);

namespace BarePitch\Domain;

final class ScoreCalculator
{
    /**
     * Calculates score from match events.
     * Never uses cached values — always recalculates from source events.
     *
     * @param array $events  Rows from match_event
     * @return array{home: int, away: int}
     */
    public static function calculate(array $events): array
    {
        $home = 0;
        $away = 0;

        foreach ($events as $event) {
            $type    = $event['event_type'];
            $side    = $event['team_side'];
            $outcome = $event['outcome'] ?? EventOutcome::None->value;

            $eventTypeEnum = EventType::tryFrom($type);
            $isScoreType   = $eventTypeEnum?->isScoreEvent() ?? false;

            if (!$isScoreType) {
                continue;
            }

            // Goal events are always scoring (a goal is definitionally successful;
            // there is no such thing as a "missed goal" in the domain).
            // Penalty events only score when outcome='scored' (vs. missed penalty).
            $isPenalty = $eventTypeEnum === EventType::Penalty;
            if ($isPenalty && $outcome !== EventOutcome::Scored->value) {
                continue;
            }

            if ($side === TeamSide::Own->value) {
                $home++;
            } else {
                $away++;
            }
        }

        return ['home' => $home, 'away' => $away];
    }
}

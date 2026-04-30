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

            $isGoal    = $type === EventType::Goal->value;
            $isPenalty = $type === EventType::Penalty->value
                && $outcome === EventOutcome::Scored->value;

            if ($isGoal || $isPenalty) {
                if ($side === TeamSide::Own->value) {
                    $home++;
                } else {
                    $away++;
                }
            }
        }

        return ['home' => $home, 'away' => $away];
    }
}

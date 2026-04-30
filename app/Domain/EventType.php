<?php

declare(strict_types=1);

namespace BarePitch\Domain;

enum EventType: string
{
    case Goal       = 'goal';
    case Penalty    = 'penalty';
    case YellowCard = 'yellow_card';
    case RedCard    = 'red_card';
    case Note       = 'note';

    public function isScoreEvent(): bool
    {
        return $this === self::Goal || $this === self::Penalty;
    }
}

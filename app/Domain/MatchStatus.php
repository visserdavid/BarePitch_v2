<?php

declare(strict_types=1);

namespace BarePitch\Domain;

enum MatchStatus: string
{
    case Planned   = 'planned';
    case Prepared  = 'prepared';
    case Active    = 'active';
    case Finished  = 'finished';

    public function canTransitionTo(self $next): bool
    {
        return match($this) {
            self::Planned  => $next === self::Prepared,
            self::Prepared => $next === self::Active,
            self::Active   => $next === self::Finished,
            self::Finished => false,
        };
    }

    public function label(): string
    {
        return match($this) {
            self::Planned  => 'Planned',
            self::Prepared => 'Prepared',
            self::Active   => 'Live',
            self::Finished => 'Finished',
        };
    }
}

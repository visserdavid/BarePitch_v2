<?php

declare(strict_types=1);

namespace BarePitch\Domain;

enum PositionLine: string
{
    case Goalkeeper = 'GK';
    case Defender   = 'DEF';
    case Midfielder = 'MID';
    case Forward    = 'FWD';

    public function label(): string
    {
        return match($this) {
            self::Goalkeeper => 'Goalkeeper',
            self::Defender   => 'Defender',
            self::Midfielder => 'Midfielder',
            self::Forward    => 'Forward',
        };
    }
}

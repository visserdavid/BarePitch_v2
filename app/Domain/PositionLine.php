<?php

declare(strict_types=1);

namespace BarePitch\Domain;

// Values must match the line_key ENUM in the database schema.
// Canonical values: GK, DEF, MID, FWD (uppercase abbreviations).
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

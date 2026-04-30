<?php

declare(strict_types=1);

namespace BarePitch\Domain;

enum GoalZone: string
{
    case TopLeft      = 'tl';
    case TopMiddle    = 'tm';
    case TopRight     = 'tr';
    case MiddleLeft   = 'ml';
    case MiddleMiddle = 'mm';
    case MiddleRight  = 'mr';
    case BottomLeft   = 'bl';
    case BottomMiddle = 'bm';
    case BottomRight  = 'br';

    /** @return string[] */
    public static function validCodes(): array
    {
        return array_map(fn(self $z) => $z->value, self::cases());
    }
}

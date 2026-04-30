<?php

declare(strict_types=1);

namespace BarePitch\Domain;

enum PeriodKey: string
{
    case Regular1 = 'regular_1';
    case Regular2 = 'regular_2';
    case Extra1   = 'extra_1';
    case Extra2   = 'extra_2';

    public function sortOrder(): int
    {
        return match($this) {
            self::Regular1 => 1,
            self::Regular2 => 2,
            self::Extra1   => 3,
            self::Extra2   => 4,
        };
    }

    public function isRegularTime(): bool
    {
        return $this === self::Regular1 || $this === self::Regular2;
    }
}

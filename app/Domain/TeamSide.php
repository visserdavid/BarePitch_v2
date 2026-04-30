<?php

declare(strict_types=1);

namespace BarePitch\Domain;

enum TeamSide: string
{
    case Own      = 'own';
    case Opponent = 'opponent';
}

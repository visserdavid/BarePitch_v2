<?php

declare(strict_types=1);

namespace BarePitch\Domain;

enum EventOutcome: string
{
    case Scored = 'scored';
    case Missed = 'missed';
    case None   = 'none';
}

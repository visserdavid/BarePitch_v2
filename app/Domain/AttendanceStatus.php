<?php

declare(strict_types=1);

namespace BarePitch\Domain;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent  = 'absent';
    case Injured = 'injured';
}

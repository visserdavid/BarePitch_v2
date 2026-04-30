<?php

declare(strict_types=1);

namespace BarePitch\Domain;

enum RoleKey: string
{
    case Coach       = 'coach';
    case Trainer     = 'trainer';
    case TeamManager = 'team_manager';
}

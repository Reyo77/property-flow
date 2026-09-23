<?php

namespace App\Enums;

enum ResidencyType: string
{
    case Owner = 'owner';
    case Tenant = 'tenant';
    case Occupant = 'occupant';

    public function label(): string
    {
        return match ($this) {
            self::Owner => __('Owner'),
            self::Tenant => __('Tenant'),
            self::Occupant => __('Occupant'),
        };
    }
}

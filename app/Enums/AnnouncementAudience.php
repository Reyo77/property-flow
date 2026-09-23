<?php

namespace App\Enums;

/**
 * Who an announcement is targeted at, within its community.
 */
enum AnnouncementAudience: string
{
    case Community = 'community';
    case Buildings = 'buildings';
    case Units = 'units';
    case ResidencyType = 'residency_type';

    public function label(): string
    {
        return match ($this) {
            self::Community => __('Everyone in the community'),
            self::Buildings => __('Selected buildings'),
            self::Units => __('Selected units'),
            self::ResidencyType => __('Owners or tenants only'),
        };
    }
}

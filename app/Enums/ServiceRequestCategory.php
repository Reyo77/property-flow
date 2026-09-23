<?php

namespace App\Enums;

enum ServiceRequestCategory: string
{
    case Plumbing = 'plumbing';
    case Electrical = 'electrical';
    case Hvac = 'hvac';
    case Appliance = 'appliance';
    case Structural = 'structural';
    case Pest = 'pest';
    case CommonArea = 'common_area';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Plumbing => __('Plumbing'),
            self::Electrical => __('Electrical'),
            self::Hvac => __('Heating / cooling'),
            self::Appliance => __('Appliance'),
            self::Structural => __('Structural'),
            self::Pest => __('Pest control'),
            self::CommonArea => __('Common area'),
            self::Other => __('Other'),
        };
    }
}

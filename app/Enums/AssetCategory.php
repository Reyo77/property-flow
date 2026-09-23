<?php

namespace App\Enums;

enum AssetCategory: string
{
    case Elevator = 'elevator';
    case Hvac = 'hvac';
    case FireSafety = 'fire_safety';
    case Pool = 'pool';
    case Generator = 'generator';
    case Landscaping = 'landscaping';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Elevator => __('Elevator'),
            self::Hvac => __('Heating / cooling'),
            self::FireSafety => __('Fire safety'),
            self::Pool => __('Pool / spa'),
            self::Generator => __('Generator'),
            self::Landscaping => __('Landscaping'),
            self::Other => __('Other'),
        };
    }
}

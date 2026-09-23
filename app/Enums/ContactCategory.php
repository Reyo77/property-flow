<?php

namespace App\Enums;

enum ContactCategory: string
{
    case Staff = 'staff';
    case Emergency = 'emergency';
    case Vendor = 'vendor';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Staff => __('Staff'),
            self::Emergency => __('Emergency'),
            self::Vendor => __('Vendor'),
            self::Other => __('Other'),
        };
    }
}

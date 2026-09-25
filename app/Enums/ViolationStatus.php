<?php

namespace App\Enums;

enum ViolationStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::Resolved => __('Resolved'),
            self::Dismissed => __('Dismissed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'amber',
            self::Resolved => 'green',
            self::Dismissed => 'zinc',
        };
    }
}

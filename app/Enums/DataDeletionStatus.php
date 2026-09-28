<?php

namespace App\Enums;

enum DataDeletionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Denied = 'denied';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Awaiting review'),
            self::Approved => __('Approved · data erased'),
            self::Denied => __('Denied'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Approved => 'green',
            self::Denied => 'red',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Pending;
    }
}

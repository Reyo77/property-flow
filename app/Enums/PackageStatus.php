<?php

namespace App\Enums;

enum PackageStatus: string
{
    case AwaitingPickup = 'awaiting_pickup';
    case PickedUp = 'picked_up';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingPickup => __('Awaiting pickup'),
            self::PickedUp => __('Picked up'),
        };
    }
}

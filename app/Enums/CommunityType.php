<?php

namespace App\Enums;

enum CommunityType: string
{
    case Condominium = 'condo';
    case Hoa = 'hoa';
    case Cooperative = 'coop';
    case Rental = 'rental';
    case MixedUse = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::Condominium => __('Condominium'),
            self::Hoa => __('HOA'),
            self::Cooperative => __('Co-op'),
            self::Rental => __('Rental'),
            self::MixedUse => __('Mixed-use'),
        };
    }
}

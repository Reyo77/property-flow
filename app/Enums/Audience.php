<?php

namespace App\Enums;

/**
 * Who a survey or consent form is for.
 */
enum Audience: string
{
    case Residents = 'residents';
    case Owners = 'owners';

    public function label(): string
    {
        return match ($this) {
            self::Residents => __('All residents'),
            self::Owners => __('Owners only'),
        };
    }
}

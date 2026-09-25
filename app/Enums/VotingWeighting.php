<?php

namespace App\Enums;

enum VotingWeighting: string
{
    /** Every eligible unit counts the same. */
    case PerUnit = 'per_unit';

    /** Each unit counts in proportion to its unit factor (its share of the common elements). */
    case UnitFactor = 'unit_factor';

    public function label(): string
    {
        return match ($this) {
            self::PerUnit => __('One unit, one vote'),
            self::UnitFactor => __('Weighted by unit factor'),
        };
    }
}

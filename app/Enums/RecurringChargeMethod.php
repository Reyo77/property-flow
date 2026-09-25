<?php

namespace App\Enums;

enum RecurringChargeMethod: string
{
    /** The same amount billed to each unit. */
    case Fixed = 'fixed';

    /** A community-wide total split between units in proportion to their unit factor. */
    case UnitFactor = 'unit_factor';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => __('Same amount per unit'),
            self::UnitFactor => __('Split by unit factor'),
        };
    }
}

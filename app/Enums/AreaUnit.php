<?php

namespace App\Enums;

enum AreaUnit: string
{
    case SquareFeet = 'sq_ft';
    case SquareMetres = 'sq_m';

    public function label(): string
    {
        return match ($this) {
            self::SquareFeet => __('sq ft'),
            self::SquareMetres => __('m²'),
        };
    }
}

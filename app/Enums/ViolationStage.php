<?php

namespace App\Enums;

/**
 * The escalation ladder: a friendly notice, then a formal warning, then fines.
 */
enum ViolationStage: string
{
    case Courtesy = 'courtesy';
    case Warning = 'warning';
    case Fine = 'fine';

    public function label(): string
    {
        return match ($this) {
            self::Courtesy => __('Courtesy notice'),
            self::Warning => __('Warning'),
            self::Fine => __('Fine'),
        };
    }
}

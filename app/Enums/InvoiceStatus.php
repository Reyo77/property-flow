<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Open = 'open';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::PartiallyPaid => __('Partially paid'),
            self::Paid => __('Paid'),
            self::Voided => __('Voided'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'amber',
            self::PartiallyPaid => 'blue',
            self::Paid => 'green',
            self::Voided => 'zinc',
        };
    }
}

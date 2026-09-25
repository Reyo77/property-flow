<?php

namespace App\Enums;

enum PaymentReversalReason: string
{
    case Refund = 'refund';
    case Nsf = 'nsf';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Refund => __('Refunded'),
            self::Nsf => __('Returned (NSF)'),
            self::Error => __('Recorded in error'),
        };
    }
}

<?php

namespace App\Enums;

enum VendorBillStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Awaiting approval'),
            self::Approved => __('Approved'),
            self::Rejected => __('Rejected'),
            self::Paid => __('Paid'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Approved => 'blue',
            self::Rejected => 'zinc',
            self::Paid => 'green',
        };
    }
}

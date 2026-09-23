<?php

namespace App\Enums;

/**
 * Who a work order is assigned to: one of the company's own staff, or an outside vendor.
 */
enum Assignee: string
{
    case Staff = 'staff';
    case Vendor = 'vendor';

    public function label(): string
    {
        return match ($this) {
            self::Staff => __('Staff member'),
            self::Vendor => __('Vendor'),
        };
    }
}

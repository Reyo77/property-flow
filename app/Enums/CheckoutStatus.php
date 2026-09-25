<?php

namespace App\Enums;

enum CheckoutStatus: string
{
    case Open = 'open';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
}

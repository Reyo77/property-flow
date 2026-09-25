<?php

namespace App\Support\Payments;

use App\Enums\CheckoutStatus;
use App\Support\Finance\Money;

final readonly class Checkout
{
    public function __construct(
        public string $reference,
        public int $unitId,
        public Money $amount,
        public CheckoutStatus $status,
        public string $redirectUrl,
    ) {}

    public function isPaid(): bool
    {
        return $this->status === CheckoutStatus::Paid;
    }
}

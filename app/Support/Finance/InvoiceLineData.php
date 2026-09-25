<?php

namespace App\Support\Finance;

use App\Models\Account;
use App\Models\ChargeType;

/**
 * One line of an invoice that is about to be issued.
 */
final readonly class InvoiceLineData
{
    public function __construct(
        public string $description,
        public Money $amount,
        public Account $account,
        public ?ChargeType $chargeType = null,
    ) {}

    public static function forChargeType(ChargeType $chargeType, Money $amount, ?string $description = null): self
    {
        return new self($description ?? $chargeType->name, $amount, $chargeType->account, $chargeType);
    }
}

<?php

namespace App\Support\Finance;

/**
 * What a billing run did for one community and month.
 */
final readonly class BillingRunResult
{
    public function __construct(
        public int $issued,
        public int $alreadyBilled,
        public int $unitsWithoutFactor,
        public int $totalCents,
    ) {}
}

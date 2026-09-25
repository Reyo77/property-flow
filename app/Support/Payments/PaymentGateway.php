<?php

namespace App\Support\Payments;

use App\Models\Unit;
use App\Models\User;
use App\Support\Finance\Money;

/**
 * An online payment provider, modelled on hosted checkout (e.g. Stripe Checkout): we create a
 * checkout, send the payer to the provider's page, and later look the checkout up by its
 * reference — from the return link or a webhook — to learn whether it was paid.
 */
interface PaymentGateway
{
    /**
     * A short name for the provider, shown to staff next to online payments.
     */
    public function name(): string;

    /**
     * Whether payments made through this gateway are simulated (no real money moves).
     */
    public function isTestMode(): bool;

    /**
     * @param  string  $returnUrl  Where the provider sends the payer afterwards; the reference is appended as `?reference=`.
     */
    public function createCheckout(Unit $unit, Money $amount, User $payer, string $returnUrl): Checkout;

    /**
     * The current state of a checkout, or null if the provider doesn't know the reference.
     */
    public function find(string $reference): ?Checkout;
}

<?php

namespace App\Support\Payments;

use App\Enums\CheckoutStatus;
use App\Models\Unit;
use App\Models\User;
use App\Support\Finance\Money;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Str;
use LogicException;

/**
 * A stand-in for a real provider while none is connected: the "hosted page" is our own test
 * checkout screen, and checkouts live in the cache for a day. No money moves.
 */
class LocalPaymentGateway implements PaymentGateway
{
    private const int TTL_SECONDS = 86_400;

    public function __construct(private readonly Repository $cache) {}

    public function name(): string
    {
        return 'Test gateway';
    }

    public function isTestMode(): bool
    {
        return true;
    }

    public function createCheckout(Unit $unit, Money $amount, User $payer, string $returnUrl): Checkout
    {
        $reference = 'local_'.Str::lower(Str::random(24));

        $this->cache->put($this->key($reference), [
            'unit_id' => $unit->id,
            'amount_cents' => $amount->cents,
            'currency' => $amount->currency,
            'status' => CheckoutStatus::Open->value,
            'return_url' => $returnUrl,
        ], self::TTL_SECONDS);

        return $this->find($reference) ?? throw new LogicException('Checkout was not stored.');
    }

    public function find(string $reference): ?Checkout
    {
        $data = $this->cache->get($this->key($reference));

        if (! is_array($data)) {
            return null;
        }

        return new Checkout(
            $reference,
            (int) $data['unit_id'],
            Money::of((int) $data['amount_cents'], (string) $data['currency']),
            CheckoutStatus::from((string) $data['status']),
            route('payments.test-checkout', $reference),
        );
    }

    /**
     * What the provider's page does when the payer clicks "Pay" or "Cancel". Returns where to send them.
     */
    public function complete(string $reference, bool $paid): ?string
    {
        $data = $this->cache->get($this->key($reference));

        if (! is_array($data) || $data['status'] !== CheckoutStatus::Open->value) {
            return null;
        }

        $data['status'] = ($paid ? CheckoutStatus::Paid : CheckoutStatus::Cancelled)->value;
        $this->cache->put($this->key($reference), $data, self::TTL_SECONDS);

        return $data['return_url'].(str_contains((string) $data['return_url'], '?') ? '&' : '?').'reference='.urlencode($reference);
    }

    private function key(string $reference): string
    {
        return "payments:local-checkout:{$reference}";
    }
}

<?php

namespace App\Actions\Finance;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\Unit;
use App\Support\Payments\PaymentGateway;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Records an online payment once the gateway says its checkout was paid. Safe to call any
 * number of times for the same checkout (return link, webhook retries): the gateway reference
 * is unique, so it is recorded exactly once.
 */
class ConfirmOnlinePayment
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly RecordPayment $recordPayment,
    ) {}

    /**
     * @return Payment|null the payment, or null if the checkout is unknown or wasn't paid
     */
    public function handle(string $reference): ?Payment
    {
        $existing = Payment::query()->withoutGlobalScopes()->where('gateway_reference', $reference)->first();

        if ($existing !== null) {
            return $existing;
        }

        $checkout = $this->gateway->find($reference);

        if ($checkout === null || ! $checkout->isPaid()) {
            return null;
        }

        $unit = Unit::query()->withoutGlobalScopes()->with('community')->findOrFail($checkout->unitId);

        try {
            return DB::transaction(function () use ($unit, $checkout, $reference): Payment {
                $payment = $this->recordPayment->handle(
                    $unit,
                    PaymentMethod::Online,
                    $checkout->amount,
                    CarbonImmutable::now($unit->community->timezone),
                    $this->gateway->name(),
                );

                $payment->forceFill(['gateway_reference' => $reference])->save();

                return $payment;
            });
        } catch (UniqueConstraintViolationException) {
            return Payment::query()->withoutGlobalScopes()->where('gateway_reference', $reference)->firstOrFail();
        }
    }
}

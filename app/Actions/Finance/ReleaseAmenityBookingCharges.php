<?php

namespace App\Actions\Finance;

use App\Models\AmenityBooking;
use App\Models\Invoice;
use Carbon\CarbonImmutable;

/**
 * When a booking is cancelled, voids its invoice if nothing has been paid on it yet. A paid
 * invoice is left alone: refunding it is a deliberate decision for a manager, not a side effect.
 */
class ReleaseAmenityBookingCharges
{
    public function __construct(private readonly VoidInvoice $voidInvoice) {}

    public function handle(AmenityBooking $booking): void
    {
        $invoice = Invoice::query()->withoutGlobalScopes()
            ->where('community_id', $booking->community_id)
            ->where('billing_key', ChargeAmenityBooking::billingKey($booking))
            ->first();

        if ($invoice === null || $invoice->isVoided() || $invoice->paidCents() > 0) {
            return;
        }

        $this->voidInvoice->handle($invoice, CarbonImmutable::now(), __('Booking cancelled'));
    }
}

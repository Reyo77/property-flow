<?php

namespace App\Actions\Finance;

use App\Enums\SystemAccount;
use App\Models\AmenityBooking;
use App\Models\Invoice;
use App\Models\Unit;
use App\Support\Finance\ChartOfAccounts;
use App\Support\Finance\InvoiceLineData;
use App\Support\Finance\Money;
use Carbon\CarbonImmutable;

/**
 * Bills a confirmed amenity booking's fee (as amenity income) and deposit (as a liability —
 * it belongs to the resident until returned) to the booking's unit. Idempotent per booking.
 * Bookings with nothing to charge, or made without a unit, are not billed.
 */
class ChargeAmenityBooking
{
    public function __construct(
        private readonly IssueInvoice $issueInvoice,
        private readonly ChartOfAccounts $chartOfAccounts,
    ) {}

    public static function billingKey(AmenityBooking $booking): string
    {
        return "amenity-booking:{$booking->id}";
    }

    public function handle(AmenityBooking $booking): ?Invoice
    {
        $fee = (int) $booking->fee_cents;
        $deposit = (int) $booking->deposit_cents;

        if ($booking->unit_id === null || $fee + $deposit === 0) {
            return null;
        }

        $unit = Unit::query()->withoutGlobalScopes()->findOrFail($booking->unit_id);
        $community = $unit->community;
        $amenity = $booking->amenity;
        $bookingDate = CarbonImmutable::parse($booking->starts_at)->setTimezone($community->timezone)->startOfDay();
        $today = CarbonImmutable::now($community->timezone)->startOfDay();
        $label = "{$amenity->name}, {$bookingDate->toFormattedDateString()}";

        $lines = [];

        if ($fee > 0) {
            $lines[] = new InvoiceLineData(__('Amenity fee: :booking', ['booking' => $label]), Money::of($fee, $community->currency), $this->chartOfAccounts->account($community, SystemAccount::AmenityFees));
        }

        if ($deposit > 0) {
            $lines[] = new InvoiceLineData(__('Security deposit: :booking', ['booking' => $label]), Money::of($deposit, $community->currency), $this->chartOfAccounts->account($community, SystemAccount::Deposits));
        }

        return $this->issueInvoice->handle(
            $unit,
            $today,
            $bookingDate->greaterThan($today) ? $bookingDate : $today,
            $lines,
            source: $booking,
            billingKey: self::billingKey($booking),
        );
    }
}

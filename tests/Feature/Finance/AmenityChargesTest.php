<?php

use App\Actions\Amenities\CancelAmenityBooking;
use App\Actions\Amenities\CreateAmenityBooking;
use App\Actions\Amenities\DecideAmenityBooking;
use App\Actions\Finance\ChargeAmenityBooking;
use App\Actions\Finance\RecordPayment;
use App\Enums\AmenityBookingStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\SystemAccount;
use App\Models\Amenity;
use App\Models\AmenityBooking;
use App\Models\Community;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\Residency;
use App\Models\Resident;
use App\Models\Unit;
use App\Support\Finance\ChartOfAccounts;
use App\Support\Finance\Money;
use App\Support\Finance\UnitLedger;
use Carbon\CarbonImmutable;

function chargeableSlot(Amenity $amenity): CarbonImmutable
{
    return $amenity->availableSlots($amenity->minBookableDate()->addDays(2))[0]['starts_at'];
}

/**
 * @return array{0: Amenity, 1: Resident, 2: Unit}
 */
function chargeableAmenity(array $attributes = []): array
{
    $community = Community::factory()->create();
    $resident = residentOf($community);
    $unit = Residency::where('resident_id', $resident->id)->sole()->unit;
    $amenity = Amenity::factory()->for($community)->create(['fee_cents' => 5000, 'deposit_cents' => 20000, ...$attributes]);

    return [$amenity, $resident, $unit];
}

it('bills the fee as amenity income and the deposit as a liability when a booking is confirmed on creation', function () {
    [$amenity, $resident, $unit] = chargeableAmenity();

    $booking = app(CreateAmenityBooking::class)->handle($amenity, $resident->user, chargeableSlot($amenity), $unit->id, null, true);

    $invoice = Invoice::withoutGlobalScopes()->sole();
    $community = $amenity->community;
    $lines = LedgerEntry::withoutGlobalScopes()->where('journal_entry_id', $invoice->journal_entry_id)->get();
    $credited = fn (SystemAccount $account) => $lines->firstWhere('account_id', app(ChartOfAccounts::class)->account($community, $account)->id)?->credit_cents;

    expect($invoice)
        ->unit_id->toBe($unit->id)
        ->total_cents->toBe(25000)
        ->billing_key->toBe(ChargeAmenityBooking::billingKey($booking))
        ->and($invoice->source->is($booking))->toBeTrue()
        ->and($invoice->due_on->toDateString())->toBe($booking->starts_at->setTimezone($community->timezone)->toDateString())
        ->and($credited(SystemAccount::AmenityFees))->toBe(5000)
        ->and($credited(SystemAccount::Deposits))->toBe(20000)
        ->and(app(UnitLedger::class)->balance($unit)->cents)->toBe(25000);
});

it('bills only the fee when the amenity has no deposit', function () {
    [$amenity, $resident, $unit] = chargeableAmenity(['deposit_cents' => null]);

    app(CreateAmenityBooking::class)->handle($amenity, $resident->user, chargeableSlot($amenity), $unit->id, null, true);

    expect(Invoice::withoutGlobalScopes()->sole()->lines()->withoutGlobalScopes()->count())->toBe(1)
        ->and(Invoice::withoutGlobalScopes()->sole()->total_cents)->toBe(5000);
});

it('does not bill free amenities or bookings made without a unit', function () {
    [$free, $resident, $unit] = chargeableAmenity(['fee_cents' => null, 'deposit_cents' => null]);
    app(CreateAmenityBooking::class)->handle($free, $resident->user, chargeableSlot($free), $unit->id, null, true);

    [$paid, $resident] = chargeableAmenity();
    app(CreateAmenityBooking::class)->handle($paid, $resident->user, chargeableSlot($paid), null, null, true);

    expect(Invoice::withoutGlobalScopes()->count())->toBe(0);
});

it('waits for approval before billing, and bills on confirmation only', function () {
    [$amenity, $resident, $unit] = chargeableAmenity(['needs_approval' => true]);
    $manager = companyAdmin($amenity->community->company);

    $booking = app(CreateAmenityBooking::class)->handle($amenity, $resident->user, chargeableSlot($amenity), $unit->id, null, true);
    expect(Invoice::withoutGlobalScopes()->count())->toBe(0);

    app(DecideAmenityBooking::class)->handle($booking, $manager, AmenityBookingStatus::Confirmed);
    expect(Invoice::withoutGlobalScopes()->sole()->total_cents)->toBe(25000);
});

it('does not bill a rejected booking', function () {
    [$amenity, $resident, $unit] = chargeableAmenity(['needs_approval' => true]);
    $booking = app(CreateAmenityBooking::class)->handle($amenity, $resident->user, chargeableSlot($amenity), $unit->id, null, true);

    app(DecideAmenityBooking::class)->handle($booking, companyAdmin($amenity->community->company), AmenityBookingStatus::Rejected);

    expect(Invoice::withoutGlobalScopes()->count())->toBe(0);
});

it('never bills the same booking twice', function () {
    [$amenity, , $unit] = chargeableAmenity();
    $booking = AmenityBooking::factory()->for($amenity)->forUnit($unit->id)->create(['fee_cents' => 5000, 'deposit_cents' => 0]);

    $first = app(ChargeAmenityBooking::class)->handle($booking);
    $second = app(ChargeAmenityBooking::class)->handle($booking);

    expect($second?->id)->toBe($first?->id)
        ->and(Invoice::withoutGlobalScopes()->count())->toBe(1);
});

it('voids the unpaid invoice when the booking is cancelled', function () {
    [$amenity, $resident, $unit] = chargeableAmenity();
    $booking = app(CreateAmenityBooking::class)->handle($amenity, $resident->user, chargeableSlot($amenity), $unit->id, null, true);

    app(CancelAmenityBooking::class)->handle($booking, companyAdmin($amenity->community->company), 'Pool closed');

    expect(Invoice::withoutGlobalScopes()->sole()->status())->toBe(InvoiceStatus::Voided)
        ->and(app(UnitLedger::class)->balance($unit)->cents)->toBe(0);
});

it('leaves a paid invoice in place when the booking is cancelled, for a manager to refund', function () {
    [$amenity, $resident, $unit] = chargeableAmenity();
    $booking = app(CreateAmenityBooking::class)->handle($amenity, $resident->user, chargeableSlot($amenity), $unit->id, null, true);
    app(RecordPayment::class)->handle($unit, PaymentMethod::Cash, Money::of(25000), CarbonImmutable::now());

    app(CancelAmenityBooking::class)->handle($booking, companyAdmin($amenity->community->company));

    expect(Invoice::withoutGlobalScopes()->sole()->status())->toBe(InvoiceStatus::Paid);
});

<?php

use App\Actions\Amenities\CancelAmenityBooking;
use App\Actions\Amenities\CreateAmenityBooking;
use App\Actions\Amenities\DecideAmenityBooking;
use App\Enums\AmenityBookingStatus;
use App\Enums\NotificationCategory;
use App\Livewire\Amenities\Show;
use App\Models\Amenity;
use App\Models\AmenityBlackout;
use App\Models\AmenityBooking;
use App\Models\Community;
use App\Models\NotificationPreference;
use App\Models\Residency;
use App\Models\Unit;
use App\Notifications\AmenityBookingStatusChanged;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

function nextBookableSlot(Amenity $amenity, int $daysAhead = 2): CarbonImmutable
{
    $date = $amenity->minBookableDate()->addDays($daysAhead);

    return $amenity->availableSlots($date)[0]['starts_at'];
}

it('books an amenity end to end through the UI and auto-confirms when no approval is needed', function () {
    Notification::fake();

    $community = Community::factory()->create();
    $resident = residentOf($community);
    $unit = Residency::where('resident_id', $resident->id)->sole()->unit;
    $amenity = Amenity::factory()->for($community)->create(['opens_at_minutes' => 9 * 60, 'closes_at_minutes' => 17 * 60, 'slot_minutes' => 60]);

    actingAs($resident->user);

    $slot = nextBookableSlot($amenity);

    Livewire::test(Show::class, ['community' => $community, 'amenity' => $amenity])
        ->set('date', $slot->clone()->setTimezone($community->timezone)->toDateString())
        ->call('selectSlot', $slot->toIso8601String())
        ->set('unit_id', (string) $unit->id)
        ->call('book')
        ->assertHasNoErrors();

    $booking = AmenityBooking::sole();

    expect($booking)
        ->amenity_id->toBe($amenity->id)
        ->unit_id->toBe($unit->id)
        ->resident_id->toBe($resident->id)
        ->status->toBe(AmenityBookingStatus::Confirmed)
        ->starts_at->equalTo($slot)->toBeTrue();

    Notification::assertSentTo($resident->user, AmenityBookingStatusChanged::class);
});

it('creates a pending booking when the amenity needs approval, then notifies on the manager\'s decision', function () {
    Notification::fake();

    $community = Community::factory()->create();
    $resident = residentOf($community);
    $amenity = Amenity::factory()->for($community)->needsApproval()->create();

    $booking = app(CreateAmenityBooking::class)->handle($amenity, $resident->user, nextBookableSlot($amenity), $resident->residencies()->value('unit_id'), null, false);

    expect($booking->status)->toBe(AmenityBookingStatus::Pending);
    Notification::assertNothingSentTo($resident->user);

    $admin = companyAdmin($community->company);
    app(DecideAmenityBooking::class)->handle($booking, $admin, AmenityBookingStatus::Confirmed);

    expect($booking->refresh())->status->toBe(AmenityBookingStatus::Confirmed)->decided_by_id->toBe($admin->id);
    Notification::assertSentTo($resident->user, AmenityBookingStatusChanged::class);
});

it('rejects a pending booking', function () {
    $community = Community::factory()->create();
    $resident = residentOf($community);
    $amenity = Amenity::factory()->for($community)->needsApproval()->create();
    $booking = app(CreateAmenityBooking::class)->handle($amenity, $resident->user, nextBookableSlot($amenity), $resident->residencies()->value('unit_id'), null, false);

    $admin = companyAdmin($community->company);
    app(DecideAmenityBooking::class)->handle($booking, $admin, AmenityBookingStatus::Rejected, 'Room already booked for a private event.');

    expect($booking->refresh())->status->toBe(AmenityBookingStatus::Rejected)->decision_notes->toBe('Room already booked for a private event.');
});

it('refuses a second booking for a slot already at capacity', function () {
    $community = Community::factory()->create();
    $resident = residentOf($community);
    $amenity = Amenity::factory()->for($community)->create(['capacity' => 1]);
    $slot = nextBookableSlot($amenity);

    app(CreateAmenityBooking::class)->handle($amenity, $resident->user, $slot, $resident->residencies()->value('unit_id'), null, false);

    expect(fn () => app(CreateAmenityBooking::class)->handle($amenity, $resident->user, $slot, $resident->residencies()->value('unit_id'), null, false))
        ->toThrow(ValidationException::class);

    expect(AmenityBooking::count())->toBe(1);
});

it('allows bookings up to capacity and no further, safe against concurrent requests via the amenity row lock', function () {
    $community = Community::factory()->create();
    $resident = residentOf($community);
    $amenity = Amenity::factory()->for($community)->create(['capacity' => 2]);
    $slot = nextBookableSlot($amenity);

    app(CreateAmenityBooking::class)->handle($amenity, $resident->user, $slot, $resident->residencies()->value('unit_id'), null, false);
    app(CreateAmenityBooking::class)->handle($amenity, $resident->user, $slot, $resident->residencies()->value('unit_id'), null, false);

    expect(fn () => app(CreateAmenityBooking::class)->handle($amenity, $resident->user, $slot, $resident->residencies()->value('unit_id'), null, false))
        ->toThrow(ValidationException::class);

    expect(AmenityBooking::where('starts_at', $slot)->count())->toBe(2);
});

it('frees the slot once a booking is cancelled', function () {
    $community = Community::factory()->create();
    $resident = residentOf($community);
    $amenity = Amenity::factory()->for($community)->create(['capacity' => 1]);
    $slot = nextBookableSlot($amenity);

    $booking = app(CreateAmenityBooking::class)->handle($amenity, $resident->user, $slot, $resident->residencies()->value('unit_id'), null, false);
    app(CancelAmenityBooking::class)->handle($booking, $resident->user);

    $rebooked = app(CreateAmenityBooking::class)->handle($amenity, $resident->user, $slot, $resident->residencies()->value('unit_id'), null, false);

    expect($rebooked->status)->toBe(AmenityBookingStatus::Confirmed);
});

it('enforces the max-bookings-per-unit limit within the rolling window', function () {
    $community = Community::factory()->create();
    $resident = residentOf($community);
    $unit = Residency::where('resident_id', $resident->id)->sole()->unit;
    $amenity = Amenity::factory()->for($community)->create([
        'capacity' => 10,
        'max_bookings_per_unit' => 1,
        'max_bookings_period_days' => 30,
    ]);

    app(CreateAmenityBooking::class)->handle($amenity, $resident->user, nextBookableSlot($amenity, 2), $unit->id, null, false);

    expect(fn () => app(CreateAmenityBooking::class)->handle($amenity, $resident->user, nextBookableSlot($amenity, 5), $unit->id, null, false))
        ->toThrow(ValidationException::class);

    expect(AmenityBooking::where('unit_id', $unit->id)->count())->toBe(1);
});

it('refuses a booking on a day the amenity is closed', function () {
    $community = Community::factory()->create();
    $resident = residentOf($community);
    $amenity = Amenity::factory()->for($community)->closedOn([1])->create();

    $monday = $amenity->minBookableDate()->next(CarbonImmutable::MONDAY);

    expect($amenity->availableSlots($monday))->toBe([]);

    $fakeMondaySlot = CarbonImmutable::create($monday->year, $monday->month, $monday->day, 10, 0, 0, $community->timezone)->utc();

    expect(fn () => app(CreateAmenityBooking::class)->handle($amenity, $resident->user, $fakeMondaySlot, $resident->residencies()->value('unit_id'), null, false))
        ->toThrow(ValidationException::class);
});

it('refuses a booking during a blackout', function () {
    $community = Community::factory()->create();
    $resident = residentOf($community);
    $amenity = Amenity::factory()->for($community)->create();
    $blackoutDate = $amenity->minBookableDate()->addDays(3);
    AmenityBlackout::factory()->for($amenity)->create([
        'starts_on' => $blackoutDate->toDateString(),
        'ends_on' => $blackoutDate->toDateString(),
    ]);

    expect($amenity->availableSlots($blackoutDate))->toBe([]);

    $blackedOutSlot = CarbonImmutable::create($blackoutDate->year, $blackoutDate->month, $blackoutDate->day, 10, 0, 0, $community->timezone)->utc();

    expect(fn () => app(CreateAmenityBooking::class)->handle($amenity, $resident->user, $blackedOutSlot, $resident->residencies()->value('unit_id'), null, false))
        ->toThrow(ValidationException::class);
});

it('refuses a booking beyond the advance-booking window', function () {
    $community = Community::factory()->create();
    $resident = residentOf($community);
    $amenity = Amenity::factory()->for($community)->create(['advance_booking_days' => 7]);

    $farDate = $amenity->minBookableDate()->addDays(30);
    expect($amenity->availableSlots($farDate))->toBe([]);

    $tooFarSlot = CarbonImmutable::create($farDate->year, $farDate->month, $farDate->day, 10, 0, 0, $community->timezone)->utc();

    expect(fn () => app(CreateAmenityBooking::class)->handle($amenity, $resident->user, $tooFarSlot, $resident->residencies()->value('unit_id'), null, false))
        ->toThrow(ValidationException::class);
});

it('refuses a booking inside the minimum notice window', function () {
    $community = Community::factory()->create();
    $resident = residentOf($community);
    $amenity = Amenity::factory()->for($community)->create(['min_notice_hours' => 48]);

    // Tomorrow's opening slot is always well under 48 hours away, no matter the time of day "now" falls on.
    $tomorrow = $amenity->minBookableDate()->addDay();
    $tooSoon = CarbonImmutable::create($tomorrow->year, $tomorrow->month, $tomorrow->day, intdiv($amenity->opens_at_minutes, 60), $amenity->opens_at_minutes % 60, 0, $community->timezone)->utc();

    expect(fn () => app(CreateAmenityBooking::class)->handle($amenity, $resident->user, $tooSoon, $resident->residencies()->value('unit_id'), null, false))
        ->toThrow(ValidationException::class);
});

it('requires accepting terms when the amenity has them', function () {
    $community = Community::factory()->create();
    $resident = residentOf($community);
    $amenity = Amenity::factory()->for($community)->create(['terms' => 'No smoking. Clean up after use.']);
    $slot = nextBookableSlot($amenity);

    expect(fn () => app(CreateAmenityBooking::class)->handle($amenity, $resident->user, $slot, $resident->residencies()->value('unit_id'), null, false))
        ->toThrow(ValidationException::class);

    $booking = app(CreateAmenityBooking::class)->handle($amenity, $resident->user, $slot, $resident->residencies()->value('unit_id'), null, true);

    expect($booking->terms_accepted_at)->not->toBeNull();
});

it('snapshots the fee and deposit onto the booking at the time it is made', function () {
    $community = Community::factory()->create();
    $resident = residentOf($community);
    $amenity = Amenity::factory()->for($community)->create(['fee_cents' => 5000, 'deposit_cents' => 20000]);

    $booking = app(CreateAmenityBooking::class)->handle($amenity, $resident->user, nextBookableSlot($amenity), $resident->residencies()->value('unit_id'), null, false);

    $amenity->update(['fee_cents' => 9999]);

    expect($booking->fee_cents)->toBe(5000)->and($booking->deposit_cents)->toBe(20000);
});

describe('cancellation notice window', function () {
    it('refuses a resident cancelling a confirmed booking too close to its start', function () {
        $community = Community::factory()->create();
        $resident = residentOf($community);
        // cancellation_notice_hours (10 days) comfortably exceeds how far out this booking is (1 day).
        $amenity = Amenity::factory()->for($community)->create(['cancellation_notice_hours' => 24 * 10]);
        $booking = app(CreateAmenityBooking::class)->handle($amenity, $resident->user, nextBookableSlot($amenity, 1), $resident->residencies()->value('unit_id'), null, false);

        expect(fn () => app(CancelAmenityBooking::class)->handle($booking, $resident->user))
            ->toThrow(ValidationException::class);

        expect($booking->refresh()->status)->toBe(AmenityBookingStatus::Confirmed);
    });

    it('allows a resident to cancel outside the notice window', function () {
        $community = Community::factory()->create();
        $resident = residentOf($community);
        $amenity = Amenity::factory()->for($community)->create(['cancellation_notice_hours' => 24]);
        $booking = app(CreateAmenityBooking::class)->handle($amenity, $resident->user, nextBookableSlot($amenity, 5), $resident->residencies()->value('unit_id'), null, false);

        app(CancelAmenityBooking::class)->handle($booking, $resident->user);

        expect($booking->refresh())->status->toBe(AmenityBookingStatus::Cancelled)->cancelled_by_id->toBe($resident->user->id);
    });

    it('lets a manager override the notice window', function () {
        $community = Community::factory()->create();
        $resident = residentOf($community);
        $admin = companyAdmin($community->company);
        $amenity = Amenity::factory()->for($community)->create(['cancellation_notice_hours' => 24 * 10]);
        $booking = app(CreateAmenityBooking::class)->handle($amenity, $resident->user, nextBookableSlot($amenity, 1), $resident->residencies()->value('unit_id'), null, false);

        app(CancelAmenityBooking::class)->handle($booking, $admin, 'Amenity closed for emergency repair.');

        expect($booking->refresh())->status->toBe(AmenityBookingStatus::Cancelled)->decision_notes->toBe('Amenity closed for emergency repair.');
    });

    it('always allows cancelling a still-pending booking regardless of notice', function () {
        $community = Community::factory()->create();
        $resident = residentOf($community);
        $amenity = Amenity::factory()->for($community)->needsApproval()->create(['cancellation_notice_hours' => 24 * 10]);
        $booking = app(CreateAmenityBooking::class)->handle($amenity, $resident->user, nextBookableSlot($amenity, 1), $resident->residencies()->value('unit_id'), null, false);

        app(CancelAmenityBooking::class)->handle($booking, $resident->user);

        expect($booking->refresh()->status)->toBe(AmenityBookingStatus::Cancelled);
    });
});

it('respects the amenity-bookings notification preference', function () {
    Notification::fake();

    $community = Community::factory()->create();
    $resident = residentOf($community);
    NotificationPreference::factory()->for($resident->user)->create(['category' => NotificationCategory::AmenityBookings, 'in_app' => false]);
    $amenity = Amenity::factory()->for($community)->create();

    app(CreateAmenityBooking::class)->handle($amenity, $resident->user, nextBookableSlot($amenity), $resident->residencies()->value('unit_id'), null, false);

    Notification::assertNotSentTo($resident->user, AmenityBookingStatusChanged::class);
});

it('generates the correct local wall-clock slot times across a daylight-saving transition', function () {
    $community = Community::factory()->create(['timezone' => 'America/Toronto']);
    $amenity = Amenity::factory()->for($community)->create(['opens_at_minutes' => 9 * 60, 'closes_at_minutes' => 11 * 60, 'slot_minutes' => 60]);

    $day = $amenity->minBookableDate()->addDay();
    while (! ($day->isDST() && ! $day->clone()->subDay()->isDST())) {
        $day = $day->addDay();
    }

    $slots = $amenity->availableSlots($day);

    $correctFirstSlot = CarbonImmutable::create($day->year, $day->month, $day->day, 9, 0, 0, 'America/Toronto')->utc();
    $naiveFirstSlot = CarbonImmutable::create($day->year, $day->month, $day->day - 1, 0, 0, 0, 'America/Toronto')->utc()->addDay()->addHours(9);

    expect($slots[0]['starts_at']->equalTo($correctFirstSlot))->toBeTrue()
        ->and($slots[0]['starts_at']->equalTo($naiveFirstSlot))->toBeFalse()
        ->and($slots[0]['starts_at']->clone()->setTimezone('America/Toronto')->hour)->toBe(9);
});

it('cannot decide on or cancel a booking from another company', function () {
    $admin = companyAdmin();
    $foreignBooking = AmenityBooking::factory()->create();

    actingAs($admin);

    expect($admin->can('decide', $foreignBooking))->toBeFalse()
        ->and($admin->can('cancel', $foreignBooking))->toBeFalse();
});

it('makes residents book for one of their own units, so the per-unit limit always applies', function (string $case) {
    $community = Community::factory()->create();
    $resident = residentOf($community);
    $amenity = Amenity::factory()->for($community)->create();
    $unitId = $case === 'no unit' ? null : Unit::factory()->for($community)->create()->id;

    expect(fn () => app(CreateAmenityBooking::class)->handle($amenity, $resident->user, nextBookableSlot($amenity), $unitId, null, false))
        ->toThrow(ValidationException::class, 'Choose one of your own units.');
})->with(['no unit', 'a neighbour\'s unit']);

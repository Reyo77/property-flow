<?php

use App\Enums\AmenityBookingStatus;
use App\Enums\CompanyRole;
use App\Models\Amenity;
use App\Models\AmenityBooking;
use App\Models\Community;
use App\Models\Resident;
use App\Models\Unit;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/**
 * @return array{0: Community, 1: Amenity, 2: Resident, 3: int}
 */
function bookableAmenity(array $attributes = []): array
{
    $community = Community::factory()->create();
    $amenity = Amenity::factory()->for($community)->create(['opens_at_minutes' => 9 * 60, 'closes_at_minutes' => 17 * 60, 'slot_minutes' => 60, 'capacity' => 1, ...$attributes]);
    $resident = residentOf($community);

    return [$community, $amenity, $resident, $resident->residencies()->value('unit_id')];
}

function firstSlot(Community $community, Amenity $amenity): string
{
    $date = $amenity->minBookableDate()->addDays(2)->toDateString();

    return getJson(route('api.v1.communities.amenities.show', ['community' => $community, 'amenity' => $amenity, 'date' => $date]))
        ->assertOk()
        ->assertJsonPath('slots.date', $date)
        ->assertJsonPath('slots.items.0.bookable', true)
        ->json('slots.items.0.starts_at');
}

it('lets a resident find a free slot, book it, and see the slot fill up', function () {
    [$community, $amenity, $resident, $unitId] = bookableAmenity();
    Sanctum::actingAs($resident->user);
    $slot = firstSlot($community, $amenity);

    postJson(route('api.v1.communities.amenity-bookings.store', $community), ['amenity_id' => $amenity->id, 'starts_at' => $slot, 'unit_id' => $unitId])
        ->assertCreated()
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonPath('data.unit.id', $unitId);

    getJson(route('api.v1.communities.amenities.show', ['community' => $community, 'amenity' => $amenity, 'date' => $amenity->minBookableDate()->addDays(2)->toDateString()]))
        ->assertJsonPath('slots.items.0.remaining', 0)
        ->assertJsonPath('slots.items.0.bookable', false);

    postJson(route('api.v1.communities.amenity-bookings.store', $community), ['amenity_id' => $amenity->id, 'starts_at' => $slot, 'unit_id' => $unitId])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('slot');
});

it('refuses a resident booking for someone else\'s unit or no unit', function (bool $withUnit) {
    [$community, $amenity, $resident] = bookableAmenity();
    Sanctum::actingAs($resident->user);
    $slot = firstSlot($community, $amenity);

    postJson(route('api.v1.communities.amenity-bookings.store', $community), array_filter([
        'amenity_id' => $amenity->id,
        'starts_at' => $slot,
        'unit_id' => $withUnit ? Unit::factory()->for($community)->create()->id : null,
    ]))->assertUnprocessable()->assertJsonValidationErrors('unit_id');
})->with(['a neighbour\'s unit' => true, 'no unit' => false]);

it('rejects an amenity from another community', function () {
    [$community, , $resident, $unitId] = bookableAmenity();
    $elsewhere = Amenity::factory()->for(Community::factory()->for($community->company))->create();
    Sanctum::actingAs($resident->user);

    postJson(route('api.v1.communities.amenity-bookings.store', $community), ['amenity_id' => $elsewhere->id, 'starts_at' => now()->addDays(3)->toIso8601String(), 'unit_id' => $unitId])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('amenity_id');
});

it('shows residents only their own bookings, and lets them cancel', function () {
    [$community, $amenity, $resident, $unitId] = bookableAmenity(['cancellation_notice_hours' => null]);
    $mine = AmenityBooking::factory()->for($amenity)->forUnit($unitId)->forResident($resident->id)->create(['booked_by_id' => $resident->user_id, 'starts_at' => now()->addDays(5), 'ends_at' => now()->addDays(5)->addHour()]);
    AmenityBooking::factory()->for($amenity)->create();
    Sanctum::actingAs($resident->user);

    getJson(route('api.v1.communities.amenity-bookings.index', $community))->assertOk()->assertJsonPath('data.*.id', [$mine->id]);

    postJson(route('api.v1.communities.amenity-bookings.cancellation.store', [$community, $mine]), ['reason' => 'Plans changed'])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
});

it('lets the team approve a pending booking, but not a resident, and not twice', function () {
    [$community, $amenity] = bookableAmenity(['needs_approval' => true]);
    $booking = AmenityBooking::factory()->for($amenity)->pending()->create();

    Sanctum::actingAs(residentOf($community)->user);
    postJson(route('api.v1.communities.amenity-bookings.decision.store', [$community, $booking]), ['decision' => 'confirmed'])->assertForbidden();

    Sanctum::actingAs(teamMember(CompanyRole::PropertyManager, $community->company, [$community]));
    postJson(route('api.v1.communities.amenity-bookings.decision.store', [$community, $booking]), ['decision' => 'maybe'])->assertUnprocessable()->assertJsonValidationErrors('decision');
    postJson(route('api.v1.communities.amenity-bookings.decision.store', [$community, $booking]), ['decision' => 'confirmed', 'notes' => 'Enjoy'])
        ->assertOk()
        ->assertJsonPath('data.status', AmenityBookingStatus::Confirmed->value)
        ->assertJsonPath('data.decision_notes', 'Enjoy');
    postJson(route('api.v1.communities.amenity-bookings.decision.store', [$community, $booking]), ['decision' => 'rejected'])->assertUnprocessable();
});

<?php

use App\Enums\CompanyRole;
use App\Models\Community;
use App\Models\GuestPass;
use App\Models\IncidentReport;
use App\Models\Package;
use App\Models\ParkingPermit;
use App\Models\Unit;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
});

function frontDesk(Community $community): User
{
    return teamMember(CompanyRole::PropertyManager, $community->company, [$community]);
}

describe('packages', function () {
    it('logs a package, shows it to its recipient only, and releases it', function () {
        $community = Community::factory()->create();
        $recipient = residentOf($community);
        Sanctum::actingAs(frontDesk($community));

        $id = postJson(route('api.v1.communities.packages.store', $community), [
            'unit_id' => $recipient->residencies()->value('unit_id'), 'resident_id' => $recipient->id, 'carrier' => 'Canada Post', 'shelf_location' => 'B3',
        ])->assertCreated()->assertJsonPath('data.status', 'awaiting_pickup')->json('data.id');

        Sanctum::actingAs($recipient->user);
        getJson(route('api.v1.communities.packages.index', $community))->assertJsonPath('data.*.id', [$id]);
        Sanctum::actingAs(residentOf($community)->user);
        getJson(route('api.v1.communities.packages.index', $community))->assertJsonCount(0, 'data');
        getJson(route('api.v1.communities.packages.show', [$community, $id]))->assertForbidden();

        Sanctum::actingAs(frontDesk($community));
        postJson(route('api.v1.communities.packages.release.store', [$community, $id]), ['released_to_name' => ''])->assertUnprocessable();
        postJson(route('api.v1.communities.packages.release.store', [$community, $id]), ['released_to_name' => 'Rita'])
            ->assertOk()
            ->assertJsonPath('data.status', 'picked_up')
            ->assertJsonPath('data.released_to_name', 'Rita');
    });

    it('does not let residents log or release packages', function () {
        $community = Community::factory()->create();
        $package = Package::factory()->for($community)->create();
        Sanctum::actingAs(residentOf($community)->user);

        postJson(route('api.v1.communities.packages.store', $community), ['carrier' => 'UPS'])->assertForbidden();
        postJson(route('api.v1.communities.packages.release.store', [$community, $package]), ['released_to_name' => 'Me'])->assertForbidden();
    });
});

describe('guest passes and visitors', function () {
    it('lets a resident issue a pass the front desk then redeems once', function () {
        $community = Community::factory()->create();
        $resident = residentOf($community);
        Sanctum::actingAs($resident->user);

        $code = postJson(route('api.v1.communities.guest-passes.store', $community), [
            'unit_id' => $resident->residencies()->value('unit_id'), 'guest_name' => 'Sam Guest', 'valid_from' => today()->toDateString(), 'valid_until' => today()->addDay()->toDateString(),
        ])->assertCreated()->json('data.code');

        postJson(route('api.v1.communities.guest-passes.store', $community), [
            'unit_id' => Unit::factory()->for($community)->create()->id, 'guest_name' => 'Sneaky', 'valid_from' => today()->toDateString(), 'valid_until' => today()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('unit_id');
        postJson(route('api.v1.communities.guest-pass-redemptions.store', $community), ['code' => $code])->assertForbidden();

        Sanctum::actingAs(frontDesk($community));
        postJson(route('api.v1.communities.guest-pass-redemptions.store', $community), ['code' => strtolower($code)])
            ->assertCreated()
            ->assertJsonPath('data.visitor_name', 'Sam Guest');
        postJson(route('api.v1.communities.guest-pass-redemptions.store', $community), ['code' => $code])->assertUnprocessable();
        postJson(route('api.v1.communities.guest-pass-redemptions.store', $community), ['code' => 'NOPE00'])->assertUnprocessable()->assertJsonValidationErrors('code');
    });

    it('shows residents only their own passes', function () {
        $community = Community::factory()->create();
        $resident = residentOf($community);
        $mine = GuestPass::factory()->for($community)->create(['resident_id' => $resident->id, 'unit_id' => $resident->residencies()->value('unit_id')]);
        GuestPass::factory()->for($community)->create();
        Sanctum::actingAs($resident->user);

        getJson(route('api.v1.communities.guest-passes.index', $community))->assertJsonPath('data.*.id', [$mine->id]);
    });

    it('checks a visitor in and out', function () {
        $community = Community::factory()->create();
        Sanctum::actingAs(frontDesk($community));

        $id = postJson(route('api.v1.communities.visitors.store', $community), ['visitor_name' => 'Pat Plumber', 'purpose' => 'Repair'])->assertCreated()->json('data.id');
        getJson(route('api.v1.communities.visitors.index', ['community' => $community, 'filter' => ['on_site' => '1']]))->assertJsonPath('data.*.id', [$id]);

        patchJson(route('api.v1.communities.visitors.update', [$community, $id]), ['checked_out' => true])->assertOk()->assertJsonPath('data.checked_out_at', fn ($value) => $value !== null);
        expect(Visitor::sole()->checked_out_at)->not->toBeNull();
    });
});

describe('parking and incidents', function () {
    it('issues and revokes a parking permit, within the per-unit limit', function () {
        $community = Community::factory()->create();
        $unit = Unit::factory()->for($community)->create();
        Sanctum::actingAs(frontDesk($community));
        $permit = fn () => postJson(route('api.v1.communities.parking-permits.store', $community), [
            'unit_id' => $unit->id, 'plate_number' => 'ABCD 123', 'starts_on' => today()->toDateString(), 'ends_on' => today()->addDays(2)->toDateString(),
        ]);

        $id = $permit()->assertCreated()->json('data.id');
        postJson(route('api.v1.communities.parking-permits.store', $community), ['unit_id' => $unit->id])->assertUnprocessable()->assertJsonValidationErrors(['plate_number', 'starts_on', 'ends_on']);

        deleteJson(route('api.v1.communities.parking-permits.destroy', [$community, $id]))->assertNoContent();
        expect(ParkingPermit::count())->toBe(0);
    });

    it('files an incident, reading a time without an offset on the community\'s clock', function () {
        $community = Community::factory()->create(['timezone' => 'America/Toronto']);
        Sanctum::actingAs(frontDesk($community));

        postJson(route('api.v1.communities.incident-reports.store', $community), [
            'title' => 'Water leak', 'description' => 'P2', 'severity' => 'high', 'occurred_at' => '2026-10-01T22:15',
        ])->assertCreated()->assertJsonPath('data.occurred_at', '2026-10-02T02:15:00+00:00');

        postJson(route('api.v1.communities.incident-reports.store', $community), [
            'title' => 'Gate', 'description' => 'x', 'severity' => 'low', 'occurred_at' => '2026-10-01T22:15:00+00:00',
        ])->assertCreated()->assertJsonPath('data.occurred_at', '2026-10-01T22:15:00+00:00');

        postJson(route('api.v1.communities.incident-reports.store', $community), ['severity' => 'apocalyptic'])->assertUnprocessable()->assertJsonValidationErrors(['title', 'severity', 'occurred_at']);
        expect(IncidentReport::count())->toBe(2);
    });
});

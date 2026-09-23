<?php

use App\Enums\CompanyRole;
use App\Enums\ResidentRecord;
use App\Livewire\Residents\Index;
use App\Livewire\Residents\Records;
use App\Livewire\Residents\Show;
use App\Models\Building;
use App\Models\Community;
use App\Models\Residency;
use App\Models\Resident;
use App\Models\Unit;
use App\Models\Vehicle;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

function residencyIn(Community $community, array $attributes = []): Residency
{
    return Residency::factory()->for(Unit::factory()->for($community))->create($attributes);
}

it('lists current residents of the community only', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $current = residencyIn($community);
    $movedOut = residencyIn($community, ['moved_out_on' => now()->subDay()]);
    $elsewhere = residencyIn(Community::factory()->for($admin->company)->create());

    actingAs($admin);

    get(route('communities.residents.index', $community))
        ->assertOk()
        ->assertSee($current->resident->name)
        ->assertDontSee($movedOut->resident->name)
        ->assertDontSee($elsewhere->resident->name);
});

it('filters residents by status, type and search term', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $building = Building::factory()->for($community)->create();
    $owner = Residency::factory()->for(Unit::factory()->inBuilding($building)->state(['number' => '1204']))->create();
    $tenant = residencyIn($community, ['type' => 'tenant']);
    $past = residencyIn($community, ['moved_out_on' => now()->subDay()]);

    actingAs($admin);

    $component = Livewire::test(Index::class, ['community' => $community]);
    $names = fn () => $component->instance()->residents()->pluck('name')->sort()->values()->all();

    expect($names())->toBe(collect([$owner, $tenant])->map->resident->pluck('name')->sort()->values()->all());

    $component->set('type', 'tenant');
    expect($names())->toBe([$tenant->resident->name]);

    $component->set('type', '')->set('status', 'past');
    expect($names())->toBe([$past->resident->name]);

    $component->set('status', 'current')->set('search', '1204');
    expect($names())->toBe([$owner->resident->name]);
});

it('forbids a manager from the residents of a community they are not assigned to', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();

    actingAs(teamMember(CompanyRole::PropertyManager, $admin->company));

    get(route('communities.residents.index', $community))->assertForbidden();
});

it('returns 404 for a resident who never lived in the community', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $elsewhere = residencyIn(Community::factory()->for($admin->company)->create());

    actingAs($admin);

    get(route('communities.residents.show', [$community, $elsewhere->resident]))->assertNotFound();
});

it('updates a resident\'s contact details', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $resident = residencyIn($community)->resident;

    actingAs($admin);

    Livewire::test(Show::class, ['community' => $community, 'resident' => $resident])
        ->call('editDetails')
        ->set('phone', '416-555-0100')
        ->set('notes', 'Prefers text messages')
        ->call('saveDetails')
        ->assertHasNoErrors();

    expect($resident->refresh())
        ->phone->toBe('416-555-0100')
        ->notes->toBe('Prefers text messages');
});

it('does not change the email a resident signs in with', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $resident = Resident::factory()->for($admin->company)->withLogin()->create();
    Residency::factory()->for(Unit::factory()->for($community))->for($resident)->create();

    actingAs($admin);

    Livewire::test(Show::class, ['community' => $community, 'resident' => $resident])
        ->call('editDetails')
        ->set('email', 'new@example.com')
        ->call('saveDetails')
        ->assertHasErrors('email');

    expect($resident->refresh()->email)->not->toBe('new@example.com');
});

it('lets board members view but not edit residents', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $resident = residencyIn($community)->resident;

    actingAs(teamMember(CompanyRole::BoardMember, $admin->company, [$community]));

    Livewire::test(Show::class, ['community' => $community, 'resident' => $resident])
        ->assertOk()
        ->call('editDetails')
        ->assertForbidden();
});

it('adds, edits and removes a resident\'s vehicles', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $resident = residencyIn($community)->resident;

    actingAs($admin);

    $records = Livewire::test(Records::class, ['resident' => $resident, 'community' => $community, 'kind' => ResidentRecord::Vehicles]);

    $records->call('create')->set('values.plate', 'ABCD-123')->set('values.make', 'Honda')->call('save')->assertHasNoErrors();
    $vehicle = Vehicle::sole();

    $records->call('edit', $vehicle->id)->set('values.colour', 'Blue')->call('save');
    expect($vehicle->refresh())->plate->toBe('ABCD-123')->colour->toBe('Blue')->model->toBeNull();

    $records->call('delete', $vehicle->id);
    expect(Vehicle::count())->toBe(0);
});

it('requires the key fields of each resident record', function (ResidentRecord $kind, string $field) {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $resident = residencyIn($community)->resident;

    actingAs($admin);

    Livewire::test(Records::class, ['resident' => $resident, 'community' => $community, 'kind' => $kind])
        ->call('create')
        ->call('save')
        ->assertHasErrors(["values.{$field}" => 'required']);
})->with([
    'vehicle plate' => [ResidentRecord::Vehicles, 'plate'],
    'pet species' => [ResidentRecord::Pets, 'species'],
    'contact phone' => [ResidentRecord::EmergencyContacts, 'phone'],
]);

it('cannot edit another resident\'s record through the records list', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $resident = residencyIn($community)->resident;
    $otherVehicle = Vehicle::factory()->for(residencyIn($community)->resident)->create();

    actingAs($admin);

    Livewire::test(Records::class, ['resident' => $resident, 'community' => $community, 'kind' => ResidentRecord::Vehicles])
        ->call('delete', $otherVehicle->id)
        ->assertNotFound();

    expect($otherVehicle->fresh())->not->toBeNull();
});

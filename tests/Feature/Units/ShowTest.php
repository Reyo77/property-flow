<?php

use App\Enums\CompanyRole;
use App\Enums\ResidencyType;
use App\Livewire\Units\Show;
use App\Models\Community;
use App\Models\Residency;
use App\Models\Resident;
use App\Models\Unit;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('adds a new person to a unit as its primary owner', function () {
    $admin = companyAdmin();
    $unit = Unit::factory()->for(Community::factory()->for($admin->company))->create();

    actingAs($admin);

    Livewire::test(Show::class, ['community' => $unit->community, 'unit' => $unit])
        ->call('openAddResident')
        ->assertSet('isPrimary', true)
        ->set('name', 'Rita Resident')
        ->set('email', 'rita@example.com')
        ->set('type', ResidencyType::Owner->value)
        ->set('movedInOn', '2024-05-01')
        ->call('addResident')
        ->assertHasNoErrors();

    expect(Residency::sole())
        ->unit_id->toBe($unit->id)
        ->community_id->toBe($unit->community_id)
        ->type->toBe(ResidencyType::Owner)
        ->is_primary->toBeTrue()
        ->moved_in_on->toDateString()->toBe('2024-05-01')
        ->resident->name->toBe('Rita Resident');
});

it('links an existing resident and moves the primary flag to them', function () {
    $admin = companyAdmin();
    $unit = Unit::factory()->for(Community::factory()->for($admin->company))->create();
    $previousPrimary = Residency::factory()->for($unit)->create(['is_primary' => true]);
    $existing = Resident::factory()->for($admin->company)->create(['name' => 'Omar Owner']);

    actingAs($admin);

    Livewire::test(Show::class, ['community' => $unit->community, 'unit' => $unit])
        ->call('openAddResident')
        ->set('residentSource', 'existing')
        ->set('residentSearch', 'Omar')
        ->assertSee('Omar Owner')
        ->set('existingResidentId', $existing->id)
        ->set('isPrimary', true)
        ->call('addResident')
        ->assertHasNoErrors();

    expect($previousPrimary->refresh()->is_primary)->toBeFalse()
        ->and($unit->residencies()->where('resident_id', $existing->id)->sole()->is_primary)->toBeTrue()
        ->and(Resident::count())->toBe(2);
});

it('does not add the same person to a unit twice', function () {
    $admin = companyAdmin();
    $unit = Unit::factory()->for(Community::factory()->for($admin->company))->create();
    $residency = Residency::factory()->for($unit)->create();

    actingAs($admin);

    Livewire::test(Show::class, ['community' => $unit->community, 'unit' => $unit])
        ->set('residentSource', 'existing')
        ->set('existingResidentId', $residency->resident_id)
        ->call('addResident')
        ->assertHasErrors('existingResidentId');
});

it('cannot link a resident from another company', function () {
    $admin = companyAdmin();
    $unit = Unit::factory()->for(Community::factory()->for($admin->company))->create();
    $foreign = Resident::factory()->create();

    actingAs($admin);

    Livewire::test(Show::class, ['community' => $unit->community, 'unit' => $unit])
        ->set('residentSource', 'existing')
        ->set('existingResidentId', $foreign->id)
        ->call('addResident')
        ->assertNotFound();

    expect(Residency::count())->toBe(0);
});

it('records a move-out and shows the resident as past', function () {
    $admin = companyAdmin();
    $unit = Unit::factory()->for(Community::factory()->for($admin->company))->create();
    $residency = Residency::factory()->for($unit)->create(['moved_in_on' => '2020-01-01']);

    actingAs($admin);

    Livewire::test(Show::class, ['community' => $unit->community, 'unit' => $unit])
        ->call('openMoveOut', $residency->id)
        ->set('movedOutOn', today()->toDateString())
        ->call('moveOut')
        ->assertHasNoErrors()
        ->assertSee('Past residents')
        ->assertSee('Nobody is recorded as living in or owning this unit.');

    expect($residency->refresh())->moved_out_on->toDateString()->toBe(today()->toDateString())->is_primary->toBeFalse();
});

it('rejects a move-out before the move-in date', function () {
    $admin = companyAdmin();
    $unit = Unit::factory()->for(Community::factory()->for($admin->company))->create();
    $residency = Residency::factory()->for($unit)->create(['moved_in_on' => '2024-06-01']);

    actingAs($admin);

    Livewire::test(Show::class, ['community' => $unit->community, 'unit' => $unit])
        ->call('openMoveOut', $residency->id)
        ->set('movedOutOn', '2024-05-01')
        ->call('moveOut')
        ->assertHasErrors('movedOutOn');

    expect($residency->refresh()->moved_out_on)->toBeNull();
});

it('lets staff view a unit but not change its residents', function () {
    $admin = companyAdmin();
    $unit = Unit::factory()->for(Community::factory()->for($admin->company))->create();

    actingAs(teamMember(CompanyRole::Staff, $admin->company, [$unit->community]));

    Livewire::test(Show::class, ['community' => $unit->community, 'unit' => $unit])
        ->assertOk()
        ->call('openAddResident')
        ->assertForbidden();
});

it('shows the unit history with who made each change', function () {
    $admin = companyAdmin();
    $unit = Unit::factory()->for(Community::factory()->for($admin->company))->create();

    actingAs($admin);

    Livewire::test(Show::class, ['community' => $unit->community, 'unit' => $unit])
        ->set('name', 'Rita Resident')
        ->call('addResident');

    get(route('communities.units.show', [$unit->community, $unit]))
        ->assertOk()
        ->assertSee('Residency created')
        ->assertSee('by '.$admin->name);
});

it('returns 404 for a unit of another community', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $otherUnit = Unit::factory()->for(Community::factory()->for($admin->company))->create();

    actingAs($admin);

    get(route('communities.units.show', [$community, $otherUnit]))->assertNotFound();
});

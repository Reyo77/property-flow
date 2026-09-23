<?php

use App\Livewire\Buildings\Index;
use App\Models\Building;
use App\Models\Community;
use App\Models\Unit;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('lists the community\'s buildings', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    Building::factory()->for($community)->create(['name' => 'North Tower']);
    Building::factory()->for(Community::factory()->for($admin->company))->create(['name' => 'Elsewhere Tower']);

    actingAs($admin);

    get(route('communities.buildings.index', $community))
        ->assertOk()
        ->assertSee('North Tower')
        ->assertDontSee('Elsewhere Tower');
});

it('forbids company members without a role', function () {
    $member = memberWithoutRole();
    $community = Community::factory()->for($member->company)->create();

    actingAs($member);

    get(route('communities.buildings.index', $community))->assertForbidden();
});

it('adds a building to the community', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->call('create')
        ->set('name', 'North Tower')
        ->set('floors', 12)
        ->call('save')
        ->assertHasNoErrors();

    expect(Building::sole())
        ->community_id->toBe($community->id)
        ->company_id->toBe($admin->company_id)
        ->name->toBe('North Tower')
        ->floors->toBe(12)
        ->address->toBeNull();
});

it('rejects a building name already used in the community', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    Building::factory()->for($community)->create(['name' => 'North Tower']);

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->set('name', 'North Tower')
        ->call('save')
        ->assertHasErrors(['name' => 'unique']);
});

it('allows the same building name in another community', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    Building::factory()->for(Community::factory()->for($admin->company))->create(['name' => 'North Tower']);

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->set('name', 'North Tower')
        ->call('save')
        ->assertHasNoErrors();
});

it('updates a building', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $building = Building::factory()->for($community)->create(['name' => 'North Tower']);

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->call('edit', $building->id)
        ->assertSet('name', 'North Tower')
        ->set('name', 'South Tower')
        ->call('save')
        ->assertHasNoErrors();

    expect($building->refresh()->name)->toBe('South Tower');
});

it('deletes an empty building', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $building = Building::factory()->for($community)->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])->call('delete', $building->id);

    expect($building->refresh()->trashed())->toBeTrue();
});

it('keeps a building that still has units', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $building = Building::factory()->for($community)->create();
    Unit::factory()->inBuilding($building)->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])->call('delete', $building->id);

    expect($building->refresh()->trashed())->toBeFalse();
});

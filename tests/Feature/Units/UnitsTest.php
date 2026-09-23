<?php

use App\Livewire\Units\Index;
use App\Models\Building;
use App\Models\Community;
use App\Models\Unit;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('forbids company members without a role', function () {
    $member = memberWithoutRole();
    $community = Community::factory()->for($member->company)->create();

    actingAs($member);

    get(route('communities.units.index', $community))->assertForbidden();
});

it('adds a unit to a building of the community', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $building = Building::factory()->for($community)->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->call('create')
        ->set('building_id', (string) $building->id)
        ->set('number', '1204')
        ->set('floor', '12')
        ->set('area', '850.5')
        ->set('unit_factor', '0.512345')
        ->set('parking', 'P1-22')
        ->call('save')
        ->assertHasNoErrors();

    expect(Unit::sole())
        ->community_id->toBe($community->id)
        ->company_id->toBe($admin->company_id)
        ->building_id->toBe($building->id)
        ->number->toBe('1204')
        ->floor->toBe(12)
        ->area->toBe('850.50')
        ->unit_factor->toBe('0.512345')
        ->parking->toBe('P1-22')
        ->locker->toBeNull();
});

it('rejects a building from another community', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $otherBuilding = Building::factory()->for(Community::factory()->for($admin->company))->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->set('building_id', (string) $otherBuilding->id)
        ->set('number', '101')
        ->call('save')
        ->assertHasErrors(['building_id' => 'exists']);

    expect(Unit::count())->toBe(0);
});

it('rejects a unit number already used in the same building', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $building = Building::factory()->for($community)->create();
    Unit::factory()->inBuilding($building)->create(['number' => '101']);

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->set('building_id', (string) $building->id)
        ->set('number', '101')
        ->call('save')
        ->assertHasErrors(['number' => 'unique']);
});

it('rejects a unit number already used outside any building', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    Unit::factory()->for($community)->create(['number' => '12 Maple Lane']);

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->set('number', '12 Maple Lane')
        ->call('save')
        ->assertHasErrors(['number' => 'unique']);
});

it('allows the same unit number in different buildings', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $towerA = Building::factory()->for($community)->create();
    $towerB = Building::factory()->for($community)->create();
    Unit::factory()->inBuilding($towerA)->create(['number' => '101']);

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->set('building_id', (string) $towerB->id)
        ->set('number', '101')
        ->call('save')
        ->assertHasNoErrors();

    expect(Unit::where('number', '101')->count())->toBe(2);
});

it('allows reusing the number of a deleted unit', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    Unit::factory()->for($community)->create(['number' => '101'])->delete();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->set('number', '101')
        ->call('save')
        ->assertHasNoErrors();
});

it('rejects invalid unit details', function (string $field, string $value) {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->set('number', '101')
        ->set($field, $value)
        ->call('save')
        ->assertHasErrors([$field]);
})->with([
    'unit factor above 100' => ['unit_factor', '100.5'],
    'unit factor with 7 decimals' => ['unit_factor', '0.1234567'],
    'negative area' => ['area', '-1'],
    'fractional floor' => ['floor', '1.5'],
]);

it('updates a unit', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $unit = Unit::factory()->for($community)->create(['number' => '101']);

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->call('edit', $unit->id)
        ->assertSet('number', '101')
        ->set('number', '102')
        ->call('save')
        ->assertHasNoErrors();

    expect($unit->refresh()->number)->toBe('102');
});

it('deletes a unit', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $unit = Unit::factory()->for($community)->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])->call('delete', $unit->id);

    expect($unit->refresh()->trashed())->toBeTrue();
});

it('filters units by search term and building', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $building = Building::factory()->for($community)->create();
    Unit::factory()->inBuilding($building)->create(['number' => '101']);
    Unit::factory()->inBuilding($building)->create(['number' => '202']);
    Unit::factory()->for($community)->create(['number' => '105']);

    actingAs($admin);

    $component = Livewire::test(Index::class, ['community' => $community]);

    $numbers = fn () => $component->instance()->units()->pluck('number')->all();

    $component->set('search', '10');
    expect($numbers())->toEqualCanonicalizing(['101', '105']);

    $component->set('buildingFilter', (string) $building->id);
    expect($numbers())->toBe(['101']);

    $component->set('search', '')->set('buildingFilter', 'none');
    expect($numbers())->toBe(['105']);
});

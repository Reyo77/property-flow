<?php

use App\Livewire\ParkingPermits\Index;
use App\Models\Community;
use App\Models\ParkingPermit;
use App\Models\Unit;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('issues a parking permit', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $unit = Unit::factory()->for($community)->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->call('create')
        ->set('unit_id', (string) $unit->id)
        ->set('plate_number', 'ABC-123')
        ->set('starts_on', now()->toDateString())
        ->set('ends_on', now()->addDay()->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    $permit = ParkingPermit::sole();

    expect($permit)->unit_id->toBe($unit->id)->plate_number->toBe('ABC-123')->issued_by_id->toBe($admin->id);
    expect($permit->isActive())->toBeTrue();
});

it('refuses to issue a permit once the unit is at its active-permit limit', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $unit = Unit::factory()->for($community)->create();
    ParkingPermit::factory()->for($community)->for($unit)->count(3)->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->set('unit_id', (string) $unit->id)
        ->set('plate_number', 'ONE-MORE')
        ->set('starts_on', now()->toDateString())
        ->set('ends_on', now()->addDay()->toDateString())
        ->call('save')
        ->assertHasErrors('unit_id');

    expect(ParkingPermit::where('plate_number', 'ONE-MORE')->exists())->toBeFalse();
});

it('allows a new permit once an old one has expired, freeing the unit\'s limit', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $unit = Unit::factory()->for($community)->create();
    ParkingPermit::factory()->for($community)->for($unit)->expired()->count(3)->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->set('unit_id', (string) $unit->id)
        ->set('plate_number', 'FRESH-1')
        ->set('starts_on', now()->toDateString())
        ->set('ends_on', now()->addDay()->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    expect(ParkingPermit::where('plate_number', 'FRESH-1')->exists())->toBeTrue();
});

it('marks an expired permit as inactive', function () {
    $permit = ParkingPermit::factory()->expired()->create();

    expect($permit->isActive())->toBeFalse();
});

it('revokes a permit', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $permit = ParkingPermit::factory()->for($community)->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])->call('delete', $permit->id);

    expect($permit->refresh()->trashed())->toBeTrue();
});

it('cannot issue a permit for a unit from another company', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $foreignUnit = Unit::factory()->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->set('unit_id', (string) $foreignUnit->id)
        ->set('plate_number', 'SNEAKY')
        ->set('starts_on', now()->toDateString())
        ->set('ends_on', now()->addDay()->toDateString())
        ->call('save')
        ->assertHasErrors('unit_id');
});

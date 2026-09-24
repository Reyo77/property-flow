<?php

use App\Enums\CompanyRole;
use App\Livewire\Amenities\Index;
use App\Models\Amenity;
use App\Models\Community;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('lists the community\'s amenities', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    Amenity::factory()->for($community)->create(['name' => 'Party Room']);
    Amenity::factory()->for(Community::factory()->for($admin->company))->create(['name' => 'Elsewhere Amenity']);

    actingAs($admin);

    get(route('communities.amenities.index', $community))
        ->assertOk()
        ->assertSee('Party Room')
        ->assertDontSee('Elsewhere Amenity');
});

it('creates, updates and deletes an amenity converting hours to minutes and dollars to cents', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->call('create')
        ->set('name', 'Rooftop Pool')
        ->set('opens_at', '07:00')
        ->set('closes_at', '21:00')
        ->set('closed_weekdays', [1])
        ->set('slot_minutes', '90')
        ->set('capacity', '3')
        ->set('needs_approval', true)
        ->set('fee', '25.50')
        ->set('deposit', '100')
        ->call('save')
        ->assertHasNoErrors();

    $amenity = Amenity::sole();

    expect($amenity)
        ->community_id->toBe($community->id)
        ->name->toBe('Rooftop Pool')
        ->opens_at_minutes->toBe(7 * 60)
        ->closes_at_minutes->toBe(21 * 60)
        ->closed_weekdays->toBe([1])
        ->slot_minutes->toBe(90)
        ->capacity->toBe(3)
        ->needs_approval->toBeTrue()
        ->fee_cents->toBe(2550)
        ->deposit_cents->toBe(10000);

    Livewire::test(Index::class, ['community' => $community])
        ->call('edit', $amenity->id)
        ->set('capacity', '5')
        ->call('save');

    expect($amenity->refresh()->capacity)->toBe(5);

    Livewire::test(Index::class, ['community' => $community])->call('delete', $amenity->id);

    expect($amenity->refresh()->trashed())->toBeTrue();
});

it('leaves fee and deposit null when left blank', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->call('create')
        ->set('name', 'Guest Suite')
        ->set('opens_at', '08:00')
        ->set('closes_at', '20:00')
        ->call('save')
        ->assertHasNoErrors();

    expect(Amenity::sole())->fee_cents->toBeNull()->deposit_cents->toBeNull();
});

it('forbids staff without manage-amenities from creating or editing, but they can view', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $amenity = Amenity::factory()->for($community)->create();
    $boardMember = teamMember(CompanyRole::BoardMember, $admin->company, [$community]);

    actingAs($boardMember);

    get(route('communities.amenities.index', $community))->assertOk();
    Livewire::test(Index::class, ['community' => $community])->call('create')->assertForbidden();
    Livewire::test(Index::class, ['community' => $community])->call('edit', $amenity->id)->assertForbidden();
});

it('lets a resident view amenities without the view-amenities permission', function () {
    $community = Community::factory()->create();
    $resident = residentOf($community);
    Amenity::factory()->for($community)->create(['name' => 'Fitness Centre']);

    actingAs($resident->user);

    get(route('communities.amenities.index', $community))->assertOk()->assertSee('Fitness Centre');
});

it('hides inactive amenities from residents but shows them to managers', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $resident = residentOf($community);
    Amenity::factory()->for($community)->inactive()->create(['name' => 'Closed For Renovation']);

    actingAs($resident->user);
    Livewire::test(Index::class, ['community' => $community])->assertDontSee('Closed For Renovation');

    actingAs($admin);
    Livewire::test(Index::class, ['community' => $community])->assertSee('Closed For Renovation');
});

it('cannot manage an amenity from another company', function () {
    $admin = companyAdmin();
    $foreignAmenity = Amenity::factory()->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => Community::factory()->for($admin->company)->create()])
        ->call('edit', $foreignAmenity->id)
        ->assertNotFound();
});

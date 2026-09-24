<?php

use App\Livewire\GuestPasses\Index;
use App\Models\Community;
use App\Models\GuestPass;
use App\Models\Residency;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('lets a resident create a guest pass for their own unit', function () {
    $community = Community::factory()->create();
    $resident = residentOf($community);
    $unit = Residency::where('resident_id', $resident->id)->sole()->unit;

    actingAs($resident->user);

    Livewire::test(Index::class, ['community' => $community])
        ->call('create')
        ->set('guest_name', 'Sam Friend')
        ->set('valid_from', now()->toDateString())
        ->set('valid_until', now()->addDay()->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    $pass = GuestPass::sole();

    expect($pass)
        ->unit_id->toBe($unit->id)
        ->resident_id->toBe($resident->id)
        ->guest_name->toBe('Sam Friend')
        ->code->toHaveLength(6);
});

it('refuses a resident creating a pass for a unit that is not theirs', function () {
    $community = Community::factory()->create();
    $resident = residentOf($community);
    $otherResident = residentOf($community);
    $otherUnit = Residency::where('resident_id', $otherResident->id)->sole()->unit;

    actingAs($resident->user);

    Livewire::test(Index::class, ['community' => $community])
        ->set('unit_id', (string) $otherUnit->id)
        ->set('guest_name', 'Sneaky Guest')
        ->set('valid_from', now()->toDateString())
        ->set('valid_until', now()->toDateString())
        ->call('save')
        ->assertHasErrors('unit_id');

    expect(GuestPass::count())->toBe(0);
});

it('lets a resident see only their own guest passes', function () {
    $community = Community::factory()->create();
    $resident = residentOf($community);
    $otherResident = residentOf($community);
    GuestPass::factory()->for($community)->create(['resident_id' => $resident->id, 'guest_name' => 'Mine']);
    GuestPass::factory()->for($community)->create(['resident_id' => $otherResident->id, 'guest_name' => 'NotMine']);

    actingAs($resident->user);

    Livewire::test(Index::class, ['community' => $community])
        ->assertSee('Mine')
        ->assertDontSee('NotMine');
});

it('lets staff with manage-visitors see every guest pass in the community', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $residentA = residentOf($community);
    $residentB = residentOf($community);
    GuestPass::factory()->for($community)->create(['resident_id' => $residentA->id, 'guest_name' => 'Alpha']);
    GuestPass::factory()->for($community)->create(['resident_id' => $residentB->id, 'guest_name' => 'Beta']);

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->assertSee('Alpha')
        ->assertSee('Beta');
});

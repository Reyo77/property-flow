<?php

use App\Livewire\EntryAuthorizations\Index;
use App\Models\Community;
use App\Models\EntryAuthorization;
use App\Models\Unit;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('adds, edits and removes an entry authorization', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $unit = Unit::factory()->for($community)->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->call('create')
        ->set('unit_id', (string) $unit->id)
        ->set('name', 'Cleaning Co.')
        ->set('relationship', 'Cleaner')
        ->call('save')
        ->assertHasNoErrors();

    $authorization = EntryAuthorization::sole();

    expect($authorization)
        ->unit_id->toBe($unit->id)
        ->name->toBe('Cleaning Co.')
        ->active->toBeTrue()
        ->created_by_id->toBe($admin->id);

    Livewire::test(Index::class, ['community' => $community])
        ->call('edit', $authorization->id)
        ->set('active', false)
        ->call('save');

    expect($authorization->refresh()->active)->toBeFalse();

    Livewire::test(Index::class, ['community' => $community])->call('delete', $authorization->id);

    expect($authorization->refresh()->trashed())->toBeTrue();
});

it('cannot manage an entry authorization from another company', function () {
    $admin = companyAdmin();
    $foreignAuthorization = EntryAuthorization::factory()->create();

    actingAs($admin);

    expect($admin->can('update', $foreignAuthorization))->toBeFalse();
});

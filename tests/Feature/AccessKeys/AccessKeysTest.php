<?php

use App\Livewire\AccessKeys\Index;
use App\Models\AccessKey;
use App\Models\AccessKeySignout;
use App\Models\Community;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('adds a key, signs it out and returns it', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->call('create')
        ->set('label', 'Pool gate fob')
        ->call('save')
        ->assertHasNoErrors();

    $key = AccessKey::sole();
    expect($key->isSignedOut())->toBeFalse();

    $component = Livewire::test(Index::class, ['community' => $community]);
    $component->call('openSignOut', $key->id)
        ->set('signed_out_to', 'Jamie Vendor')
        ->call('signOut')
        ->assertHasNoErrors();

    expect($key->refresh()->isSignedOut())->toBeTrue();
    expect($key->currentSignout())->signed_out_to->toBe('Jamie Vendor')->signed_out_by_id->toBe($admin->id);

    $component->call('returnKey', $key->id);

    expect($key->refresh()->isSignedOut())->toBeFalse();
});

it('flags an overdue signout', function () {
    $key = AccessKey::factory()->create();
    $signout = AccessKeySignout::factory()->for($key)->overdue()->create();

    expect($signout->isOverdue())->toBeTrue();
});

it('does not flag a returned signout as overdue', function () {
    $key = AccessKey::factory()->create();
    $signout = AccessKeySignout::factory()->for($key)->overdue()->returned()->create();

    expect($signout->isOverdue())->toBeFalse();
});

it('cannot sign out a key from another company', function () {
    $admin = companyAdmin();
    $foreignKey = AccessKey::factory()->create();

    actingAs($admin);

    expect($admin->can('update', $foreignKey))->toBeFalse();
});

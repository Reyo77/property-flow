<?php

use App\Livewire\ContactMessages\Index;
use App\Models\Community;
use App\Models\ContactMessage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('forbids company members without a role', function () {
    $member = memberWithoutRole();
    $community = Community::factory()->for($member->company)->create();

    actingAs($member);

    get(route('communities.contact-messages.index', $community))->assertForbidden();
});

it('lists only the community\'s own messages, newest first', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $older = ContactMessage::factory()->for($community)->create(['name' => 'Older Message', 'created_at' => now()->subDay()]);
    $newer = ContactMessage::factory()->for($community)->create(['name' => 'Newer Message']);
    $otherCommunity = Community::factory()->for($admin->company)->create();
    ContactMessage::factory()->for($otherCommunity)->create(['name' => 'Other Community Message']);

    actingAs($admin);

    $messages = Livewire::test(Index::class, ['community' => $community])
        ->instance()
        ->messages();

    expect($messages->pluck('name')->all())->toBe(['Newer Message', 'Older Message']);
});

it('marks a message as read', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $message = ContactMessage::factory()->for($community)->create();

    actingAs($admin);

    expect($message->isRead())->toBeFalse();

    Livewire::test(Index::class, ['community' => $community])->call('markRead', $message->id);

    expect($message->fresh()->isRead())->toBeTrue();
});

it('deletes a message', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $message = ContactMessage::factory()->for($community)->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])->call('delete', $message->id);

    expect(ContactMessage::count())->toBe(0);
});

it('returns 404 for a message from a different community', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $otherCommunity = Community::factory()->for($admin->company)->create();
    $message = ContactMessage::factory()->for($otherCommunity)->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->call('markRead', $message->id)
        ->assertNotFound();
});

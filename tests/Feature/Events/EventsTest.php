<?php

use App\Enums\CompanyRole;
use App\Enums\RsvpStatus;
use App\Livewire\Events\Index;
use App\Models\Community;
use App\Models\Event;
use App\Models\EventRsvp;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('lists upcoming events by default and past events on the past tab', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    Event::factory()->for($community)->create(['title' => 'Annual BBQ']);
    Event::factory()->for($community)->past()->create(['title' => 'Old Meeting']);

    actingAs($admin);

    get(route('communities.events.index', $community))
        ->assertOk()
        ->assertSee('Annual BBQ')
        ->assertDontSee('Old Meeting');

    Livewire::test(Index::class, ['community' => $community])
        ->set('tab', 'past')
        ->assertSee('Old Meeting')
        ->assertDontSee('Annual BBQ');
});

it('lets residents view and RSVP to events in their community', function () {
    $community = Community::factory()->create();
    $event = Event::factory()->for($community)->create();
    $resident = residentOf($community);

    actingAs($resident->user);

    Livewire::test(Index::class, ['community' => $community])
        ->call('rsvp', $event->id, RsvpStatus::Going->value)
        ->assertHasNoErrors();

    expect(EventRsvp::sole())
        ->event_id->toBe($event->id)
        ->user_id->toBe($resident->user_id)
        ->status->toBe(RsvpStatus::Going);
});

it('changes an existing RSVP rather than creating a second one', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $event = Event::factory()->for($community)->create();

    actingAs($admin);

    $component = Livewire::test(Index::class, ['community' => $community]);
    $component->call('rsvp', $event->id, RsvpStatus::Maybe->value);
    $component->call('rsvp', $event->id, RsvpStatus::Going->value);

    expect(EventRsvp::count())->toBe(1)
        ->and(EventRsvp::sole()->status)->toBe(RsvpStatus::Going);
});

it('forbids residents from events in communities they do not live in', function () {
    $resident = residentWithLogin();
    $otherCommunity = Community::factory()->for($resident->company)->create();

    actingAs($resident->user);

    get(route('communities.events.index', $otherCommunity))->assertForbidden();
});

it('creates an event', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->call('create')
        ->set('title', 'Annual BBQ')
        ->set('starts_at', '2026-07-01T18:00')
        ->set('ends_at', '2026-07-01T20:00')
        ->call('save')
        ->assertHasNoErrors();

    expect(Event::sole())
        ->community_id->toBe($community->id)
        ->title->toBe('Annual BBQ')
        ->created_by_id->toBe($admin->id)
        ->starts_at->format('Y-m-d H:i')->toBe('2026-07-01 18:00')
        ->ends_at->format('Y-m-d H:i')->toBe('2026-07-01 20:00');
});

it('rejects an end time before the start time', function () {
    actingAs(companyAdmin());
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->set('title', 'Bad Event')
        ->set('starts_at', '2026-07-01T20:00')
        ->set('ends_at', '2026-07-01T18:00')
        ->call('save')
        ->assertHasErrors(['ends_at' => 'after']);
});

it('updates and deletes an event', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $event = Event::factory()->for($community)->create(['title' => 'Old Title']);

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->call('edit', $event->id)
        ->assertSet('title', 'Old Title')
        ->set('title', 'New Title')
        ->call('save')
        ->assertHasNoErrors();

    expect($event->refresh()->title)->toBe('New Title');

    Livewire::test(Index::class, ['community' => $community])->call('delete', $event->id);

    expect($event->refresh()->trashed())->toBeTrue();
});

it('residents cannot create, edit or delete events', function () {
    $community = Community::factory()->create();
    $event = Event::factory()->for($community)->create();
    $resident = residentOf($community);

    actingAs($resident->user);

    $component = Livewire::test(Index::class, ['community' => $community]);
    $component->call('create')->assertForbidden();
});

it('board members can RSVP but not manage events', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $event = Event::factory()->for($community)->create();

    actingAs(teamMember(CompanyRole::BoardMember, $admin->company, [$community]));

    Livewire::test(Index::class, ['community' => $community])
        ->call('rsvp', $event->id, RsvpStatus::Going->value)
        ->assertHasNoErrors();

    Livewire::test(Index::class, ['community' => $community])->call('create')->assertForbidden();
});

it('cannot RSVP to an event in another company', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $foreignEvent = Event::factory()->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->call('rsvp', $foreignEvent->id, RsvpStatus::Going->value)
        ->assertNotFound();
});

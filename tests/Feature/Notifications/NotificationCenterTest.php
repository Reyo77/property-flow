<?php

use App\Actions\Announcements\PublishAnnouncement;
use App\Enums\NotificationCategory;
use App\Livewire\NotificationBell;
use App\Livewire\Notifications\Index;
use App\Livewire\Settings\Notifications as NotificationSettings;
use App\Models\Announcement;
use App\Models\Community;
use App\Models\NotificationPreference;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('shows the unread count and recent notifications in the bell', function () {
    $community = Community::factory()->create();
    $resident = residentOf($community);
    $announcement = Announcement::factory()->for($community)->draft()->create(['title' => 'Water shutoff']);
    app(PublishAnnouncement::class)->handle($announcement);

    actingAs($resident->user);

    Livewire::test(NotificationBell::class)
        ->assertSet('unreadCount', 1)
        ->assertSee('Water shutoff');
});

it('marks a notification read and navigates to it when opened', function () {
    $community = Community::factory()->create();
    $resident = residentOf($community);
    $announcement = Announcement::factory()->for($community)->draft()->create();
    app(PublishAnnouncement::class)->handle($announcement);

    actingAs($resident->user);

    $notification = $resident->user->notifications()->sole();

    Livewire::test(NotificationBell::class)
        ->call('open', $notification->id)
        ->assertRedirect(route('communities.announcements.index', $community));

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('marks all notifications read at once', function () {
    $community = Community::factory()->create();
    $resident = residentOf($community);
    foreach (range(1, 3) as $i) {
        app(PublishAnnouncement::class)->handle(Announcement::factory()->for($community)->draft()->create());
    }

    actingAs($resident->user);

    expect($resident->user->unreadNotifications()->count())->toBe(3);

    Livewire::test(NotificationBell::class)->call('markAllAsRead');

    expect($resident->user->fresh()->unreadNotifications()->count())->toBe(0);
});

it('lists notifications on the full page and marks them read individually', function () {
    $community = Community::factory()->create();
    $resident = residentOf($community);
    $announcement = Announcement::factory()->for($community)->draft()->create(['title' => 'Elevator maintenance']);
    app(PublishAnnouncement::class)->handle($announcement);

    actingAs($resident->user);

    get(route('notifications.index'))->assertOk()->assertSee('Elevator maintenance');

    $notification = $resident->user->notifications()->sole();

    Livewire::test(Index::class)->call('open', $notification->id);

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('only shows a user their own notifications', function () {
    $community = Community::factory()->create();
    $resident = residentOf($community);
    $otherResident = residentOf($community);
    $announcement = Announcement::factory()->for($community)->draft()->create();
    app(PublishAnnouncement::class)->handle($announcement);

    actingAs($otherResident->user);

    expect(Livewire::test(NotificationBell::class)->get('unreadCount'))->toBe(1);

    $otherNotification = $resident->user->notifications()->sole();

    Livewire::test(NotificationBell::class)->call('open', $otherNotification->id)->assertNoRedirect();

    expect($otherNotification->fresh()->read_at)->toBeNull();
});

it('saves notification preferences and stops sending disabled categories', function () {
    $community = Community::factory()->create();
    $resident = residentOf($community);

    actingAs($resident->user);

    Livewire::test(NotificationSettings::class)
        ->assertSet('inApp.announcements', true)
        ->set('inApp.announcements', false)
        ->call('save');

    expect(NotificationPreference::inAppEnabled($resident->user, NotificationCategory::Announcements))->toBeFalse();

    $announcement = Announcement::factory()->for($community)->draft()->create();
    app(PublishAnnouncement::class)->handle($announcement);

    expect($resident->user->notifications()->count())->toBe(0);
});

it('re-enabling a preference after disabling it resumes notifications', function () {
    $community = Community::factory()->create();
    $resident = residentOf($community);

    actingAs($resident->user);

    Livewire::test(NotificationSettings::class)->set('inApp.announcements', false)->call('save');
    Livewire::test(NotificationSettings::class)->set('inApp.announcements', true)->call('save');

    $announcement = Announcement::factory()->for($community)->draft()->create();
    app(PublishAnnouncement::class)->handle($announcement);

    expect($resident->user->notifications()->count())->toBe(1);
});

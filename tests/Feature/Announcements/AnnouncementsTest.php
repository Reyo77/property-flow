<?php

use App\Enums\AnnouncementAudience;
use App\Enums\CompanyRole;
use App\Enums\NotificationCategory;
use App\Enums\ResidencyType;
use App\Livewire\Announcements\Index;
use App\Models\Announcement;
use App\Models\Building;
use App\Models\Community;
use App\Models\NotificationPreference;
use App\Models\Residency;
use App\Notifications\AnnouncementPublished;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('publishes a community-wide announcement immediately and notifies every current resident', function () {
    Notification::fake();

    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $current = residentOf($community);
    $movedOut = residentOf($community, ['moved_out_on' => now()->subDay()]);

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->call('create')
        ->set('title', 'Water shutoff')
        ->set('body', 'Water will be off tomorrow from 9am to noon.')
        ->set('audience_type', AnnouncementAudience::Community->value)
        ->call('save')
        ->assertHasNoErrors();

    $announcement = Announcement::sole();

    expect($announcement)
        ->community_id->toBe($community->id)
        ->created_by_id->toBe($admin->id)
        ->audience_type->toBe(AnnouncementAudience::Community)
        ->published_at->not->toBeNull();

    Notification::assertSentTo($current->user, AnnouncementPublished::class);
    Notification::assertNotSentTo($movedOut->user, AnnouncementPublished::class);
});

it('requires a title and body', function () {
    actingAs(companyAdmin());
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->call('save')
        ->assertHasErrors(['title' => 'required', 'body' => 'required']);
});

describe('targeting', function () {
    it('only notifies residents of the targeted buildings', function () {
        Notification::fake();

        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $towerA = Building::factory()->for($community)->create();
        $towerB = Building::factory()->for($community)->create();
        $residentA = residentOf($community);
        Residency::where('resident_id', $residentA->id)->first()->unit->update(['building_id' => $towerA->id]);
        $residentB = residentOf($community);
        Residency::where('resident_id', $residentB->id)->first()->unit->update(['building_id' => $towerB->id]);

        actingAs($admin);

        Livewire::test(Index::class, ['community' => $community])
            ->call('create')
            ->set('title', 'Tower A notice')
            ->set('body', 'For Tower A residents only.')
            ->set('audience_type', AnnouncementAudience::Buildings->value)
            ->set('building_ids', [$towerA->id])
            ->call('save')
            ->assertHasNoErrors();

        Notification::assertSentTo($residentA->user, AnnouncementPublished::class);
        Notification::assertNotSentTo($residentB->user, AnnouncementPublished::class);
    });

    it('only notifies residents of the targeted units', function () {
        Notification::fake();

        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $targetResident = residentOf($community);
        $targetUnit = Residency::where('resident_id', $targetResident->id)->first()->unit;
        $otherResident = residentOf($community);

        actingAs($admin);

        Livewire::test(Index::class, ['community' => $community])
            ->call('create')
            ->set('title', 'Unit-specific notice')
            ->set('body', 'For one unit.')
            ->set('audience_type', AnnouncementAudience::Units->value)
            ->set('unit_ids', [$targetUnit->id])
            ->call('save')
            ->assertHasNoErrors();

        Notification::assertSentTo($targetResident->user, AnnouncementPublished::class);
        Notification::assertNotSentTo($otherResident->user, AnnouncementPublished::class);
    });

    it('requires at least one building or unit for that audience type', function (string $audience, string $field) {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();

        actingAs($admin);

        Livewire::test(Index::class, ['community' => $community])
            ->set('title', 'x')
            ->set('body', 'y')
            ->set('audience_type', $audience)
            ->call('save')
            ->assertHasErrors([$field => 'required']);
    })->with([
        'buildings' => [AnnouncementAudience::Buildings->value, 'building_ids'],
        'units' => [AnnouncementAudience::Units->value, 'unit_ids'],
    ]);

    it('only notifies residents of the matching residency type', function () {
        Notification::fake();

        $community = Community::factory()->create();
        $admin = companyAdmin($community->company);
        $owner = residentOf($community, ['type' => ResidencyType::Owner]);
        $tenant = residentOf($community, ['type' => ResidencyType::Tenant]);

        actingAs($admin);

        Livewire::test(Index::class, ['community' => $community])
            ->call('create')
            ->set('title', 'Owners meeting')
            ->set('body', 'AGM notice for owners.')
            ->set('audience_type', AnnouncementAudience::ResidencyType->value)
            ->set('residency_type', ResidencyType::Owner->value)
            ->call('save')
            ->assertHasNoErrors();

        Notification::assertSentTo($owner->user, AnnouncementPublished::class);
        Notification::assertNotSentTo($tenant->user, AnnouncementPublished::class);
    });
});

describe('scheduling', function () {
    it('does not publish or notify when scheduled for later', function () {
        Notification::fake();

        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $resident = residentOf($community);

        actingAs($admin);

        Livewire::test(Index::class, ['community' => $community])
            ->call('create')
            ->set('title', 'AGM')
            ->set('body', 'Save the date.')
            ->set('audience_type', AnnouncementAudience::Community->value)
            ->set('scheduleForLater', true)
            ->set('publish_at', now()->addDays(3)->format('Y-m-d\TH:i'))
            ->call('save')
            ->assertHasNoErrors();

        $announcement = Announcement::sole();

        expect($announcement->published_at)->toBeNull()
            ->and($announcement->isScheduled())->toBeTrue();

        Notification::assertNothingSent();
    });

    it('rejects a schedule date in the past', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();

        actingAs($admin);

        Livewire::test(Index::class, ['community' => $community])
            ->set('title', 'AGM')
            ->set('body', 'x')
            ->set('audience_type', AnnouncementAudience::Community->value)
            ->set('scheduleForLater', true)
            ->set('publish_at', now()->subDay()->format('Y-m-d\TH:i'))
            ->call('save')
            ->assertHasErrors('publish_at');
    });

    it('publishes and notifies once its scheduled time arrives via the scheduler command', function () {
        Notification::fake();

        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $resident = residentOf($community);
        $announcement = Announcement::factory()->for($community)->create(['publish_at' => now()->subMinute()]);

        Artisan::call('announcements:publish-due');

        expect($announcement->refresh())->published_at->not->toBeNull();
        Notification::assertSentTo($resident->user, AnnouncementPublished::class);
    });

    it('does not publish an announcement before its scheduled time', function () {
        $community = Community::factory()->create();
        $announcement = Announcement::factory()->for($community)->create(['publish_at' => now()->addDay()]);

        Artisan::call('announcements:publish-due');

        expect($announcement->refresh()->published_at)->toBeNull();
    });

    it('lets a manager publish a scheduled announcement immediately', function () {
        Notification::fake();

        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $announcement = Announcement::factory()->for($community)->scheduled()->create();

        actingAs($admin);

        Livewire::test(Index::class, ['community' => $community])->call('publishNow', $announcement->id);

        expect($announcement->refresh()->published_at)->not->toBeNull();
    });
});

it('respects a resident\'s notification preference', function () {
    Notification::fake();

    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $resident = residentOf($community);
    NotificationPreference::factory()->for($resident->user)->create([
        'category' => NotificationCategory::Announcements,
        'in_app' => false,
    ]);

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->call('create')
        ->set('title', 'x')
        ->set('body', 'y')
        ->set('audience_type', AnnouncementAudience::Community->value)
        ->call('save');

    Notification::assertNotSentTo($resident->user, AnnouncementPublished::class);
});

it('pins and unpins an announcement', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $announcement = Announcement::factory()->for($community)->published()->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])->call('togglePin', $announcement->id);
    expect($announcement->refresh()->pinned)->toBeTrue();

    Livewire::test(Index::class, ['community' => $community])->call('togglePin', $announcement->id);
    expect($announcement->refresh()->pinned)->toBeFalse();
});

it('deletes an announcement', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $announcement = Announcement::factory()->for($community)->published()->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])->call('delete', $announcement->id);

    expect($announcement->fresh()->trashed())->toBeTrue();
});

describe('visibility', function () {
    it('shows residents only published announcements matching their audience', function () {
        $community = Community::factory()->create();
        $owner = residentOf($community, ['type' => ResidencyType::Owner]);
        $tenant = residentOf($community, ['type' => ResidencyType::Tenant]);
        Announcement::factory()->for($community)->published()->create(['title' => 'For everyone', 'audience_type' => AnnouncementAudience::Community]);
        Announcement::factory()->for($community)->published()->create(['title' => 'For owners', 'audience_type' => AnnouncementAudience::ResidencyType, 'residency_type' => ResidencyType::Owner]);
        Announcement::factory()->for($community)->draft()->create(['title' => 'Still a draft']);

        actingAs($owner->user);
        $titles = Livewire::test(Index::class, ['community' => $community])->instance()->announcements()->pluck('title')->all();
        expect($titles)->toContain('For everyone', 'For owners')->not->toContain('Still a draft');

        actingAs($tenant->user);
        $titles = Livewire::test(Index::class, ['community' => $community])->instance()->announcements()->pluck('title')->all();
        expect($titles)->toContain('For everyone')->not->toContain('For owners', 'Still a draft');
    });

    it('lets board members see all published announcements but not drafts', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        Announcement::factory()->for($community)->published()->create(['title' => 'Published one', 'audience_type' => AnnouncementAudience::ResidencyType, 'residency_type' => ResidencyType::Owner]);
        Announcement::factory()->for($community)->draft()->create(['title' => 'A draft']);

        actingAs(teamMember(CompanyRole::BoardMember, $admin->company, [$community]));

        $titles = Livewire::test(Index::class, ['community' => $community])->instance()->announcements()->pluck('title')->all();

        expect($titles)->toBe(['Published one']);
    });

    it('lets managers see drafts and scheduled announcements', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        Announcement::factory()->for($community)->draft()->create(['title' => 'A draft']);
        Announcement::factory()->for($community)->scheduled()->create(['title' => 'Scheduled']);

        actingAs($admin);

        $titles = Livewire::test(Index::class, ['community' => $community])->instance()->announcements()->pluck('title')->all();

        expect($titles)->toContain('A draft', 'Scheduled');
    });

    it('forbids residents from managing announcements', function () {
        $community = Community::factory()->create();
        $resident = residentOf($community);

        actingAs($resident->user);

        Livewire::test(Index::class, ['community' => $community])
            ->assertOk()
            ->call('create')
            ->assertForbidden();
    });
});

it('cannot browse another company\'s announcements', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $foreignAnnouncement = Announcement::factory()->published()->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->call('edit', $foreignAnnouncement->id)
        ->assertNotFound();
});

it('returns 404 when accessing announcements of a community in another company', function () {
    $community = Community::factory()->create();

    actingAs(companyAdmin());

    get(route('communities.announcements.index', $community))->assertNotFound();
});

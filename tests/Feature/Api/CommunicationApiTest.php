<?php

use App\Enums\AnnouncementAudience;
use App\Enums\DocumentVisibility;
use App\Enums\ResidencyType;
use App\Enums\ServiceRequestStatus;
use App\Models\Announcement;
use App\Models\Community;
use App\Models\Document;
use App\Models\Event;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\ServiceRequestStatusChanged;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(fn () => Storage::fake('local'));

describe('communities', function () {
    it('lists a resident\'s home communities and lets them read one', function () {
        $home = Community::factory()->create(['name' => 'Harbour Towers']);
        Community::factory()->for($home->company)->create();
        Sanctum::actingAs(residentOf($home)->user);

        getJson(route('api.v1.communities.index'))->assertOk()->assertJsonPath('data.*.name', ['Harbour Towers']);
        getJson(route('api.v1.communities.show', $home))->assertOk()->assertJsonPath('data.timezone', $home->timezone);
    });
});

describe('announcements', function () {
    it('shows a resident only the published announcements meant for them', function () {
        $community = Community::factory()->create();
        $tenant = residentOf($community, ['type' => ResidencyType::Tenant]);
        $everyone = Announcement::factory()->for($community)->published()->create(['title' => 'Water shut-off']);
        Announcement::factory()->for($community)->published()->create(['audience_type' => AnnouncementAudience::ResidencyType, 'residency_type' => ResidencyType::Owner]);
        $draft = Announcement::factory()->for($community)->draft()->create();
        Sanctum::actingAs($tenant->user);

        getJson(route('api.v1.communities.announcements.index', $community))->assertOk()->assertJsonPath('data.*.title', ['Water shut-off'])->assertJsonPath('meta.total', 1);
        getJson(route('api.v1.communities.announcements.show', [$community, $draft]))->assertForbidden();
        getJson(route('api.v1.communities.announcements.show', [$community, $everyone]))->assertOk();
    });
});

describe('events', function () {
    it('takes an RSVP and reports the counts and your own answer', function () {
        $community = Community::factory()->create();
        $event = Event::factory()->for($community)->create();
        Sanctum::actingAs(residentOf($community)->user);

        putJson(route('api.v1.communities.events.rsvp.update', [$community, $event]), ['status' => 'going'])
            ->assertOk()
            ->assertJsonPath('data.rsvps.going', 1)
            ->assertJsonPath('data.rsvps.mine', 'going');

        putJson(route('api.v1.communities.events.rsvp.update', [$community, $event]), ['status' => 'maybe'])
            ->assertJsonPath('data.rsvps.going', 0)
            ->assertJsonPath('data.rsvps.maybe', 1);

        putJson(route('api.v1.communities.events.rsvp.update', [$community, $event]), ['status' => 'perhaps'])->assertUnprocessable()->assertJsonValidationErrors('status');
    });

    it('filters upcoming and past events', function () {
        $community = Community::factory()->create();
        $upcoming = Event::factory()->for($community)->create();
        Event::factory()->for($community)->past()->create();
        Sanctum::actingAs(residentOf($community)->user);

        getJson(route('api.v1.communities.events.index', ['community' => $community, 'filter' => ['when' => 'upcoming']]))->assertJsonPath('data.*.id', [$upcoming->id]);
        getJson(route('api.v1.communities.events.index', ['community' => $community, 'filter' => ['when' => 'soon']]))->assertUnprocessable();
    });
});

describe('documents', function () {
    it('lists only the documents a tenant may see, and downloads them', function () {
        $community = Community::factory()->create();
        $forResidents = Document::factory()->for($community)->withVersion()->create(['title' => 'House rules']);
        $ownersOnly = Document::factory()->for($community)->withVersion()->create(['visibility' => DocumentVisibility::Owners]);
        Storage::disk('local')->put($forResidents->currentVersion->disk_path, 'PDF');
        Sanctum::actingAs(residentOf($community, ['type' => ResidencyType::Tenant])->user);

        $url = getJson(route('api.v1.communities.documents.index', $community))
            ->assertOk()
            ->assertJsonPath('data.*.title', ['House rules'])
            ->json('data.0.file.download_url');

        get($url)->assertOk()->assertDownload('document.pdf');
        get(route('api.v1.communities.documents.download', [$community, $ownersOnly]))->assertForbidden();
    });
});

describe('residents', function () {
    it('filters by type and search, and rejects a bad status', function () {
        $community = Community::factory()->create();
        $owner = residentOf($community, ['type' => ResidencyType::Owner]);
        residentOf($community, ['type' => ResidencyType::Tenant]);
        Sanctum::actingAs(companyAdmin($community->company));

        getJson(route('api.v1.communities.residents.index', ['community' => $community, 'filter' => ['type' => 'owner']]))
            ->assertOk()
            ->assertJsonPath('data.*.id', [$owner->id])
            ->assertJsonPath('data.0.residencies.0.type', 'owner');

        getJson(route('api.v1.communities.residents.index', ['community' => $community, 'filter' => ['search' => $owner->name]]))->assertJsonPath('data.*.id', [$owner->id]);
        getJson(route('api.v1.communities.residents.index', ['community' => $community, 'filter' => ['status' => 'ancient']]))->assertUnprocessable();
    });
});

describe('notifications', function () {
    it('lists, marks one and then all as read, only ever the user\'s own', function () {
        $community = Community::factory()->create();
        $user = residentOf($community)->user;
        $request = ServiceRequest::factory()->for($community)->create();
        $user->notify(new ServiceRequestStatusChanged($request, ServiceRequestStatus::Assigned));
        $user->notify(new ServiceRequestStatusChanged($request, ServiceRequestStatus::Resolved));
        User::factory()->create()->notify(new ServiceRequestStatusChanged($request, ServiceRequestStatus::Assigned));
        Sanctum::actingAs($user);

        $id = getJson(route('api.v1.notifications.index', ['filter' => ['unread' => '1']]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.type', 'service-request-status-changed')
            ->assertJsonPath('data.0.payload.service_request_id', $request->id)
            ->json('data.0.id');

        patchJson(route('api.v1.notifications.update', $id))->assertOk()->assertJsonPath('data.read_at', fn ($value) => $value !== null);
        getJson(route('api.v1.notifications.index', ['filter' => ['unread' => '1']]))->assertJsonCount(1, 'data');

        postJson(route('api.v1.notifications.read-all'))->assertNoContent();
        expect($user->unreadNotifications()->count())->toBe(0);

        $theirs = User::query()->whereKeyNot($user->id)->sole()->notifications()->value('id');
        patchJson(route('api.v1.notifications.update', $theirs))->assertNotFound();
    });
});

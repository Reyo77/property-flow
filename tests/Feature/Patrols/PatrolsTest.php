<?php

use App\Events\FrontDeskActivity;
use App\Livewire\PatrolRoutes\Index;
use App\Livewire\PatrolRoutes\Show;
use App\Models\Community;
use App\Models\PatrolCheckpoint;
use App\Models\PatrolRoute;
use App\Models\PatrolScan;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('creates a patrol route and adds checkpoints in order', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->call('create')
        ->set('name', 'Night patrol')
        ->call('save')
        ->assertHasNoErrors();

    $route = PatrolRoute::sole();

    Livewire::test(Show::class, ['community' => $community, 'patrolRoute' => $route])
        ->set('name', 'Lobby')
        ->call('addCheckpoint')
        ->set('name', 'Garage')
        ->call('addCheckpoint');

    expect($route->checkpoints()->pluck('name', 'position')->all())->toBe([1 => 'Lobby', 2 => 'Garage']);
    expect($route->checkpoints->pluck('qr_token'))->each->not->toBeEmpty();
});

it('scans a checkpoint via its QR-code URL and broadcasts the activity', function () {
    Event::fake([FrontDeskActivity::class]);

    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $route = PatrolRoute::factory()->for($community)->create();
    $checkpoint = PatrolCheckpoint::factory()->for($route)->create(['name' => 'Pool gate']);

    actingAs($admin);

    get(route('patrol-scan', $checkpoint->qr_token))->assertOk();

    $scan = PatrolScan::sole();

    expect($scan)->patrol_checkpoint_id->toBe($checkpoint->id)->scanned_by_id->toBe($admin->id);

    Event::assertDispatched(FrontDeskActivity::class, fn (FrontDeskActivity $event) => $event->communityId === $community->id && $event->type === 'patrol_scan');
});

it('refuses a scan from someone without manage-patrols', function () {
    $community = Community::factory()->create();
    $resident = residentOf($community);
    $route = PatrolRoute::factory()->for($community)->create();
    $checkpoint = PatrolCheckpoint::factory()->for($route)->create();

    actingAs($resident->user);

    get(route('patrol-scan', $checkpoint->qr_token))->assertForbidden();

    expect(PatrolScan::count())->toBe(0);
});

it('404s for an unknown QR token', function () {
    $admin = companyAdmin();

    actingAs($admin);

    get(route('patrol-scan', 'not-a-real-token'))->assertNotFound();
});

it('renders a scannable QR image for a checkpoint', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $route = PatrolRoute::factory()->for($community)->create();
    $checkpoint = PatrolCheckpoint::factory()->for($route)->create();

    actingAs($admin);

    get(route('communities.patrol-checkpoints.qr', [$community, $checkpoint]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml');
});

it('reports a checkpoint as missed when it was not scanned that day, and scanned when it was', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $route = PatrolRoute::factory()->for($community)->create();
    $scanned = PatrolCheckpoint::factory()->for($route)->create(['name' => 'Scanned checkpoint', 'position' => 1]);
    $missed = PatrolCheckpoint::factory()->for($route)->create(['name' => 'Missed checkpoint', 'position' => 2]);
    PatrolScan::factory()->for($scanned)->create(['scanned_at' => now()]);

    actingAs($admin);

    $summary = collect($route->scanSummaryFor(now($community->timezone)))->keyBy(fn ($entry) => $entry['checkpoint']->name);

    expect($summary['Scanned checkpoint']['last_scan_at'])->not->toBeNull();
    expect($summary['Missed checkpoint']['last_scan_at'])->toBeNull();
});

it('404s scanning a checkpoint belonging to another company\'s route', function () {
    $admin = companyAdmin();
    $foreignRoute = PatrolRoute::factory()->create();
    $foreignCheckpoint = PatrolCheckpoint::factory()->for($foreignRoute)->create();

    actingAs($admin);

    get(route('patrol-scan', $foreignCheckpoint->qr_token))->assertNotFound();
});

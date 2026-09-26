<?php

use App\Enums\CompanyRole;
use App\Enums\IncidentSeverity;
use App\Livewire\IncidentReports\Index;
use App\Livewire\IncidentReports\Show;
use App\Models\Community;
use App\Models\IncidentReport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    Storage::fake('local');
});

it('records when it happened on the community\'s own clock', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create(['timezone' => 'America/Toronto']);
    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->call('create')
        ->set('title', 'Water leak')
        ->set('description', 'P2 ceiling')
        ->set('severity', IncidentSeverity::Medium->value)
        ->set('occurred_at', '2026-10-01T22:15')
        ->call('save')
        ->assertHasNoErrors();

    expect(IncidentReport::sole()->occurred_at->utc()->toDateTimeString())->toBe('2026-10-02 02:15:00');
});

it('files an incident report with photos', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->call('create')
        ->set('title', 'Broken gate motor')
        ->set('description', 'The main gate stopped closing after the storm.')
        ->set('severity', IncidentSeverity::High->value)
        ->set('occurred_at', now()->format('Y-m-d\TH:i'))
        ->set('photos', [UploadedFile::fake()->image('gate.jpg')])
        ->call('save')
        ->assertHasNoErrors();

    $incident = IncidentReport::sole();

    expect($incident)
        ->community_id->toBe($community->id)
        ->title->toBe('Broken gate motor')
        ->severity->toBe(IncidentSeverity::High)
        ->reported_by_id->toBe($admin->id)
        ->isResolved()->toBeFalse();

    expect($incident->attachments()->count())->toBe(1);
});

it('marks an incident resolved with notes', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $incident = IncidentReport::factory()->for($community)->create();

    actingAs($admin);

    Livewire::test(Show::class, ['community' => $community, 'incidentReport' => $incident])
        ->set('resolution_notes', 'Motor replaced.')
        ->call('resolve')
        ->assertHasNoErrors();

    expect($incident->refresh())->isResolved()->toBeTrue()->resolution_notes->toBe('Motor replaced.');
});

it('does not give residents access to incident reports', function () {
    $community = Community::factory()->create();
    $resident = residentOf($community);

    actingAs($resident->user);

    get(route('communities.incident-reports.index', $community))->assertForbidden();
});

it('lets a board member view but not create incident reports', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $boardMember = teamMember(CompanyRole::BoardMember, $admin->company, [$community]);

    actingAs($boardMember);

    get(route('communities.incident-reports.index', $community))->assertOk();
    Livewire::test(Index::class, ['community' => $community])->call('create')->assertForbidden();
});

it('cannot view an incident report from another company', function () {
    $admin = companyAdmin();
    $foreignIncident = IncidentReport::factory()->create();

    actingAs($admin);

    expect($admin->can('view', $foreignIncident))->toBeFalse();
});

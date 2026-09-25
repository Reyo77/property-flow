<?php

use App\Actions\Governance\CloseBallot;
use App\Enums\BallotStatus;
use App\Enums\CompanyRole;
use App\Enums\MeetingKind;
use App\Enums\ResidencyType;
use App\Livewire\Dashboard;
use App\Livewire\Governance\BallotForm;
use App\Livewire\Governance\Ballots;
use App\Livewire\Governance\BallotShow;
use App\Livewire\Governance\Meetings;
use App\Livewire\Governance\MeetingShow;
use App\Models\Ballot;
use App\Models\BallotVote;
use App\Models\Community;
use App\Models\Meeting;
use App\Models\MeetingAttendance;
use App\Models\Residency;
use App\Models\Resident;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\travelTo;

beforeEach(fn () => travelTo(CarbonImmutable::parse('2026-10-10 12:00')));

/**
 * @return array{0: Unit, 1: User}
 */
function ownerOf(Community $community, string $factor = '25', ResidencyType $type = ResidencyType::Owner): array
{
    $unit = Unit::factory()->for($community)->create(['unit_factor' => $factor]);
    $resident = Resident::factory()->for($community->company)->withLogin()->create();
    Residency::factory()->for($unit)->for($resident)->create(['type' => $type, 'moved_in_on' => '2020-01-01']);

    return [$unit, $resident->user];
}

describe('ballot builder', function () {
    it('writes a draft ballot in the community\'s time zone, then publishes it', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create(['timezone' => 'America/Toronto']);
        actingAs($admin);

        Livewire::test(BallotForm::class, ['community' => $community])
            ->set('title', 'Approve the 2027 budget')
            ->set('opens_at', '2026-10-12T09:00')
            ->set('closes_at', '2026-10-26T17:00')
            ->set('questions.0.title', 'Do you approve the budget?')
            ->call('addQuestion')
            ->set('questions.1.title', 'Elect Pat Lee to the board?')
            ->call('removeOption', 1, 2)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $ballot = Ballot::sole();
        expect($ballot->opens_at->toDateTimeString())->toBe('2026-10-12 13:00:00')
            ->and($ballot->closes_at->toDateTimeString())->toBe('2026-10-26 21:00:00')
            ->and($ballot->questions()->get()->map(fn ($q) => $q->options()->pluck('label')->all())->all())->toBe([['Yes', 'No', 'Abstain'], ['Yes', 'No']])
            ->and($ballot->status())->toBe(BallotStatus::Draft);

        Livewire::test(BallotShow::class, ['community' => $community, 'ballot' => $ballot])->call('publish');

        expect($ballot->fresh()?->status())->toBe(BallotStatus::Upcoming);
    });

    it('validates the ballot', function (string $field, mixed $value, ?string $error = null) {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        actingAs($admin);

        Livewire::test(BallotForm::class, ['community' => $community])
            ->set('title', 'Vote')
            ->set('questions.0.title', 'Question?')
            ->set($field, $value)
            ->call('save')
            ->assertHasErrors($error ?? $field);
    })->with([
        'closes before it opens' => ['closes_at', '2026-10-01T09:00'],
        'quorum over 100' => ['quorum_percent', 101],
        'duplicate options' => ['questions.0.options.1', ' yes', 'questions.0.options'],
        'blank question' => ['questions.0.title', ''],
    ]);

    it('refuses to edit a published ballot', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $ballot = Ballot::factory()->for($community)->open()->withQuestion()->create();
        actingAs($admin);

        get(route('communities.ballots.edit', [$community, $ballot]))->assertForbidden();
    });
});

describe('voting page', function () {
    it('lets an owner vote for their unit and shows results only once closed', function () {
        $community = Community::factory()->create();
        [$unit, $owner] = ownerOf($community);
        $ballot = Ballot::factory()->for($community)->open()->withQuestion()->create();
        $question = $ballot->questions()->sole();
        actingAs($owner);

        Livewire::test(BallotShow::class, ['community' => $community, 'ballot' => $ballot])
            ->assertSee('Cast vote')
            ->assertDontSee('data-test="results"', false)
            ->set("choices.{$unit->id}.{$question->id}", (string) $question->options()->where('label', 'Yes')->value('id'))
            ->call('vote', $unit->id)
            ->assertHasNoErrors()
            ->assertSee('Voted');

        expect(BallotVote::sole()->unit_id)->toBe($unit->id);

        travelTo(CarbonImmutable::parse('2026-10-20 12:00'));
        app(CloseBallot::class)->handle($ballot);

        Livewire::test(BallotShow::class, ['community' => $community, 'ballot' => $ballot->fresh()])
            ->assertSee('Quorum met')
            ->assertSee('100.00%');
    });

    it('shows an error instead of voting when an answer is missing', function () {
        $community = Community::factory()->create();
        [$unit, $owner] = ownerOf($community);
        $ballot = Ballot::factory()->for($community)->open()->withQuestion()->create();
        actingAs($owner);

        Livewire::test(BallotShow::class, ['community' => $community, 'ballot' => $ballot])
            ->call('vote', $unit->id)
            ->assertHasErrors();

        expect(BallotVote::count())->toBe(0);
    });

    it('lets an owner appoint a proxy, who then votes for that unit', function () {
        $community = Community::factory()->create();
        [$unit, $owner] = ownerOf($community);
        [, $neighbour] = ownerOf($community);
        $ballot = Ballot::factory()->for($community)->open()->withQuestion()->create();
        $question = $ballot->questions()->sole();
        actingAs($owner);

        Livewire::test(BallotShow::class, ['community' => $community, 'ballot' => $ballot])
            ->set("proxyHolder.{$unit->id}", (string) $neighbour->id)
            ->call('appointProxy', $unit->id)
            ->assertHasNoErrors()
            ->assertSee("{$neighbour->name} is your proxy");

        actingAs($neighbour);
        Livewire::test(BallotShow::class, ['community' => $community, 'ballot' => $ballot])
            ->assertSee('as proxy')
            ->set("choices.{$unit->id}.{$question->id}", (string) $question->options()->where('label', 'No')->value('id'))
            ->call('vote', $unit->id)
            ->assertHasNoErrors();

        expect(BallotVote::sole())->unit_id->toBe($unit->id)->cast_by_id->toBe($neighbour->id)->ballot_proxy_id->not->toBeNull();
    });

    it('shows a tenant the ballot but gives them nothing to vote with', function () {
        $community = Community::factory()->create();
        [, $tenant] = ownerOf($community, type: ResidencyType::Tenant);
        $ballot = Ballot::factory()->for($community)->open()->withQuestion()->create();
        actingAs($tenant);

        Livewire::test(BallotShow::class, ['community' => $community, 'ballot' => $ballot])
            ->assertOk()
            ->assertDontSee('Cast vote')
            ->assertDontSee('Appoint proxy');
    });
});

describe('access', function () {
    it('hides draft ballots and board meetings from residents', function () {
        $community = Community::factory()->create();
        [, $owner] = ownerOf($community);
        $draft = Ballot::factory()->for($community)->withQuestion()->create(['title' => 'Secret draft']);
        Ballot::factory()->for($community)->open()->withQuestion()->create(['title' => 'Budget vote']);
        $boardMeeting = Meeting::factory()->for($community)->create(['kind' => MeetingKind::Board, 'title' => 'Board session']);
        Meeting::factory()->for($community)->create(['title' => 'AGM 2026']);
        actingAs($owner);

        Livewire::test(Ballots::class, ['community' => $community])->assertSee('Budget vote')->assertDontSee('Secret draft');
        Livewire::test(Meetings::class, ['community' => $community])->assertSee('AGM 2026')->assertDontSee('Board session');
        get(route('communities.ballots.show', [$community, $draft]))->assertForbidden();
        get(route('communities.meetings.show', [$community, $boardMeeting]))->assertForbidden();
        get(route('communities.ballots.create', $community))->assertForbidden();
    });

    it('keeps residents of other communities and staff without governance access out', function () {
        $community = Community::factory()->create();
        $ballot = Ballot::factory()->for($community)->open()->withQuestion()->create();
        [, $outsider] = ownerOf(Community::factory()->for($community->company)->create());

        actingAs($outsider);
        get(route('communities.ballots.index', $community))->assertForbidden();
        get(route('communities.ballots.show', [$community, $ballot]))->assertForbidden();

        actingAs(teamMember(CompanyRole::Vendor, $community->company, [$community]));
        get(route('communities.meetings.index', $community))->assertForbidden();
    });

    it('lets staff view but not run governance, and the board run it', function () {
        $community = Community::factory()->create();
        $meeting = Meeting::factory()->for($community)->create();

        actingAs(teamMember(CompanyRole::Staff, $community->company, [$community]));
        get(route('communities.meetings.show', [$community, $meeting]))->assertOk();
        Livewire::test(Meetings::class, ['community' => $community])->call('create')->assertForbidden();

        actingAs(teamMember(CompanyRole::BoardMember, $community->company, [$community]));
        Livewire::test(Meetings::class, ['community' => $community])->call('create')->assertOk();
    });

    it('returns 404 for another company\'s ballot or meeting', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $foreignBallot = Ballot::factory()->open()->withQuestion()->create();
        $foreignMeeting = Meeting::factory()->create();
        actingAs($admin);

        get(route('communities.ballots.show', [$foreignBallot->community_id, $foreignBallot->id]))->assertNotFound();
        get(route('communities.meetings.show', [$foreignMeeting->community_id, $foreignMeeting->id]))->assertNotFound();
        get(route('communities.ballots.show', [$community, $foreignBallot->id]))->assertNotFound();
    });
});

describe('meeting page', function () {
    it('schedules an AGM, checks owners in until quorum, publishes minutes and closes', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        [$first] = ownerOf($community, '30');
        [$second] = ownerOf($community, '70');
        actingAs($admin);

        Livewire::test(Meetings::class, ['community' => $community])
            ->call('create')
            ->set('title', 'AGM 2026')
            ->set('quorum_percent', 50)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $meeting = Meeting::sole();
        expect($meeting->agendaItems()->count())->toBe(6);

        $page = Livewire::test(MeetingShow::class, ['community' => $community, 'meeting' => $meeting])
            ->assertSee('No quorum yet')
            ->set('attendance_unit_id', (string) $first->id)
            ->call('checkIn')
            ->assertSee('30.00%')
            ->set('attendance_unit_id', (string) $second->id)
            ->set('attendance_mode', 'proxy')
            ->call('checkIn')
            ->assertSee('Quorum present')
            ->set('minutes', 'Quorum present. 2027 budget approved.')
            ->call('saveMinutes', true)
            ->call('close');

        expect(MeetingAttendance::where('meeting_id', $meeting->id)->count())->toBe(2)
            ->and($meeting->fresh())->isClosed()->toBeTrue()->hasPublishedMinutes()->toBeTrue();

        $page->set('attendance_unit_id', (string) $first->id)->call('checkIn')->assertHasErrors('attendance_unit_id');
    });

    it('shows residents published minutes only', function () {
        $community = Community::factory()->create();
        [, $owner] = ownerOf($community);
        $meeting = Meeting::factory()->for($community)->create(['minutes' => 'Draft notes nobody should see yet']);
        actingAs($owner);

        // Not in the page at all — including Livewire's serialised component state.
        get(route('communities.meetings.show', [$community, $meeting]))->assertOk()->assertDontSee('Draft notes', false);

        $meeting->forceFill(['minutes' => 'Budget approved.', 'minutes_published_at' => now()])->save();

        get(route('communities.meetings.show', [$community, $meeting]))->assertSee('Budget approved.');
    });
});

it('links a resident\'s home card to their community\'s ballots and meetings', function () {
    $community = Community::factory()->create();
    [, $owner] = ownerOf($community);
    actingAs($owner);

    Livewire::test(Dashboard::class)
        ->assertSeeHtml(route('communities.ballots.index', $community))
        ->assertSeeHtml(route('communities.meetings.index', $community));
});

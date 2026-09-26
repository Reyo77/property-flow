<?php

use App\Actions\Governance\CloseBallot;
use App\Enums\CompanyRole;
use App\Enums\MeetingKind;
use App\Enums\ResidencyType;
use App\Models\Ballot;
use App\Models\BallotVote;
use App\Models\Community;
use App\Models\Meeting;
use App\Models\Residency;
use App\Models\Resident;
use App\Models\Unit;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/**
 * @return array{0: Unit, 1: User}
 */
function apiOwnerOf(Community $community, ResidencyType $type = ResidencyType::Owner): array
{
    $unit = Unit::factory()->for($community)->create(['unit_factor' => '10']);
    $resident = Resident::factory()->for($community->company)->withLogin()->create();
    Residency::factory()->for($unit)->for($resident)->create(['type' => $type, 'moved_in_on' => '2020-01-01']);

    return [$unit, $resident->user];
}

/**
 * @return array<int, int> option id chosen for each question id, by label
 */
function choicesFor(Ballot $ballot, string $label): array
{
    return $ballot->questions()->with('options')->get()
        ->mapWithKeys(fn ($question) => [$question->id => $question->options->firstWhere('label', $label)?->id])->all();
}

describe('voting', function () {
    it('shows an owner their unit, takes one secret vote, and refuses a second', function () {
        $community = Community::factory()->create();
        [$unit, $owner] = apiOwnerOf($community);
        $ballot = Ballot::factory()->for($community)->open()->withQuestion()->create();
        Sanctum::actingAs($owner);

        getJson(route('api.v1.communities.ballots.show', [$community, $ballot]))
            ->assertOk()
            ->assertJsonPath('my_units.0.unit.id', $unit->id)
            ->assertJsonPath('my_units.0.voted_at', null)
            ->assertJsonMissingPath('data.results')
            ->assertJsonCount(3, 'data.questions.0.options');

        postJson(route('api.v1.communities.ballots.votes.store', [$community, $ballot]), ['unit_id' => $unit->id, 'choices' => choicesFor($ballot, 'Yes')])
            ->assertCreated()
            ->assertExactJsonStructure(['data' => ['unit_id', 'cast_at']]);

        postJson(route('api.v1.communities.ballots.votes.store', [$community, $ballot]), ['unit_id' => $unit->id, 'choices' => choicesFor($ballot, 'No')])->assertUnprocessable();
        expect(BallotVote::count())->toBe(1);
    });

    it('refuses a tenant, a neighbour\'s unit, and an incomplete vote', function () {
        $community = Community::factory()->create();
        [$unit, $owner] = apiOwnerOf($community);
        [$neighbourUnit] = apiOwnerOf($community);
        [, $tenant] = apiOwnerOf($community, ResidencyType::Tenant);
        $ballot = Ballot::factory()->for($community)->open()->withQuestion()->withQuestion('Second?')->create();

        Sanctum::actingAs($owner);
        postJson(route('api.v1.communities.ballots.votes.store', [$community, $ballot]), ['unit_id' => $neighbourUnit->id, 'choices' => choicesFor($ballot, 'Yes')])->assertForbidden();
        postJson(route('api.v1.communities.ballots.votes.store', [$community, $ballot]), ['unit_id' => $unit->id, 'choices' => array_slice(choicesFor($ballot, 'Yes'), 0, 1, true)])->assertUnprocessable();

        Sanctum::actingAs($tenant);
        getJson(route('api.v1.communities.ballots.show', [$community, $ballot]))->assertJsonPath('my_units', []);
        expect(BallotVote::count())->toBe(0);
    });

    it('shows results only once the ballot is closed', function () {
        $community = Community::factory()->create();
        [$unit, $owner] = apiOwnerOf($community);
        $ballot = Ballot::factory()->for($community)->open()->withQuestion()->create();
        Sanctum::actingAs($owner);
        postJson(route('api.v1.communities.ballots.votes.store', [$community, $ballot]), ['unit_id' => $unit->id, 'choices' => choicesFor($ballot, 'Yes')])->assertCreated();

        $ballot->forceFill(['closes_at' => now()->subMinute()])->save();
        app(CloseBallot::class)->handle($ballot->fresh());

        getJson(route('api.v1.communities.ballots.show', [$community, $ballot]))
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.results.voted_units', 1)
            ->assertJsonPath('data.results.questions.0.options.0.label', 'Yes');
    });

    it('lets an owner appoint a proxy who then votes, and revoke only an unused proxy', function () {
        $community = Community::factory()->create();
        [$unit, $owner] = apiOwnerOf($community);
        [, $neighbour] = apiOwnerOf($community);
        $ballot = Ballot::factory()->for($community)->open()->withQuestion()->create();
        Sanctum::actingAs($owner);

        getJson(route('api.v1.communities.ballots.show', [$community, $ballot]))->assertJsonPath('proxy_candidates.*.id', [$neighbour->id]);
        postJson(route('api.v1.communities.ballots.proxies.store', [$community, $ballot]), ['unit_id' => $unit->id, 'holder_id' => User::factory()->create()->id])
            ->assertUnprocessable()->assertJsonValidationErrors('holder_id');

        $proxyId = postJson(route('api.v1.communities.ballots.proxies.store', [$community, $ballot]), ['unit_id' => $unit->id, 'holder_id' => $neighbour->id])
            ->assertCreated()->json('data.id');

        Sanctum::actingAs($neighbour);
        deleteJson(route('api.v1.communities.ballots.proxies.destroy', [$community, $ballot, $proxyId]))->assertForbidden();
        getJson(route('api.v1.communities.ballots.show', [$community, $ballot]))->assertJsonFragment(['as_proxy' => true]);
        postJson(route('api.v1.communities.ballots.votes.store', [$community, $ballot]), ['unit_id' => $unit->id, 'choices' => choicesFor($ballot, 'No')])->assertCreated();

        Sanctum::actingAs($owner);
        deleteJson(route('api.v1.communities.ballots.proxies.destroy', [$community, $ballot, $proxyId]))->assertUnprocessable();
        expect(BallotVote::sole())->ballot_proxy_id->toBe($proxyId);
    });

    it('hides draft ballots from residents', function () {
        $community = Community::factory()->create();
        $draft = Ballot::factory()->for($community)->withQuestion()->create(['published_at' => null]);
        [, $owner] = apiOwnerOf($community);
        Sanctum::actingAs($owner);

        getJson(route('api.v1.communities.ballots.index', $community))->assertJsonCount(0, 'data');
        getJson(route('api.v1.communities.ballots.show', [$community, $draft]))->assertForbidden();

        Sanctum::actingAs(teamMember(CompanyRole::BoardMember, $community->company, [$community]));
        getJson(route('api.v1.communities.ballots.index', ['community' => $community, 'filter' => ['status' => 'draft']]))->assertJsonPath('data.*.id', [$draft->id]);
        getJson(route('api.v1.communities.ballots.index', ['community' => $community, 'filter' => ['status' => 'maybe']]))->assertUnprocessable();
    });
});

describe('meetings', function () {
    it('hides board meetings and draft minutes from residents', function () {
        $community = Community::factory()->create();
        $agm = Meeting::factory()->for($community)->create(['minutes' => 'Draft notes', 'minutes_published_at' => null]);
        $board = Meeting::factory()->for($community)->create(['kind' => MeetingKind::Board]);
        [, $owner] = apiOwnerOf($community);
        Sanctum::actingAs($owner);

        getJson(route('api.v1.communities.meetings.index', $community))->assertJsonPath('data.*.id', [$agm->id]);
        getJson(route('api.v1.communities.meetings.show', [$community, $board]))->assertForbidden();
        getJson(route('api.v1.communities.meetings.show', [$community, $agm]))->assertJsonMissingPath('data.minutes')->assertDontSee('Draft notes');

        $agm->forceFill(['minutes_published_at' => now()])->save();
        getJson(route('api.v1.communities.meetings.show', [$community, $agm]))->assertJsonPath('data.minutes.text', 'Draft notes');
    });
});

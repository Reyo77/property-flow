<?php

use App\Actions\Governance\CastVote;
use App\Actions\Governance\CloseBallot;
use App\Actions\Governance\GrantProxy;
use App\Actions\Governance\PublishBallot;
use App\Actions\Governance\RevokeProxy;
use App\Enums\BallotStatus;
use App\Enums\CompanyRole;
use App\Enums\ResidencyType;
use App\Enums\VotingWeighting;
use App\Models\Ballot;
use App\Models\BallotVote;
use App\Models\Community;
use App\Models\Residency;
use App\Models\Resident;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\artisan;
use function Pest\Laravel\travelTo;

beforeEach(fn () => travelTo(CarbonImmutable::parse('2026-10-10 12:00')));

/**
 * A unit with the given factor, and (unless null) a resident of the given type with a login.
 *
 * @return array{0: Unit, 1: User|null}
 */
function votingUnit(Community $community, string $factor = '25', ?ResidencyType $type = ResidencyType::Owner): array
{
    $unit = Unit::factory()->for($community)->create(['unit_factor' => $factor]);

    if ($type === null) {
        return [$unit, null];
    }

    $resident = Resident::factory()->for($community->company)->withLogin()->create();
    Residency::factory()->for($unit)->for($resident)->create(['type' => $type, 'moved_in_on' => '2020-01-01']);

    return [$unit, $resident->user];
}

function openBallot(Community $community, array $attributes = []): Ballot
{
    return Ballot::factory()->for($community)->open()->withQuestion()->create($attributes);
}

/**
 * The option id for a label on the ballot's first question (or the given question).
 */
function option(Ballot $ballot, string $label, int $question = 0): int
{
    return $ballot->questions()->get()[$question]->options()->where('label', $label)->valueOrFail('id');
}

/**
 * @return array<int, int>
 */
function choose(Ballot $ballot, string ...$labels): array
{
    $choices = [];

    foreach ($ballot->questions()->get() as $index => $question) {
        $choices[$question->id] = option($ballot, $labels[$index] ?? $labels[0], $index);
    }

    return $choices;
}

function vote(Ballot $ballot, Unit $unit, User $voter, string ...$labels): BallotVote
{
    return app(CastVote::class)->handle($ballot, $unit, $voter, choose($ballot, ...($labels ?: ['Yes'])));
}

describe('who can vote', function () {
    it('lets an owner vote for their unit, weighted by its unit factor', function () {
        $community = Community::factory()->create();
        [$unit, $owner] = votingUnit($community, '12.5');
        $ballot = openBallot($community);

        $vote = vote($ballot, $unit, $owner);

        expect($vote)->unit_id->toBe($unit->id)->cast_by_id->toBe($owner->id)->ballot_proxy_id->toBeNull()
            ->and($vote->weight)->toBe('12.500000');
    });

    it('refuses tenants, occupants and former owners', function (?ResidencyType $type, bool $movedOut) {
        $community = Community::factory()->create();
        [$unit] = votingUnit($community);
        $resident = Resident::factory()->for($community->company)->withLogin()->create();
        Residency::factory()->for($unit)->for($resident)->create([
            'type' => $type,
            'moved_in_on' => '2020-01-01',
            'moved_out_on' => $movedOut ? '2026-01-01' : null,
        ]);

        vote(openBallot($community), $unit, $resident->user);
    })->with([
        'tenant' => [ResidencyType::Tenant, false],
        'occupant' => [ResidencyType::Occupant, false],
        'former owner' => [ResidencyType::Owner, true],
    ])->throws(AuthorizationException::class);

    it('refuses a unit with no current owner, even for the manager', function () {
        $community = Community::factory()->create();
        [$unit, $tenant] = votingUnit($community, type: ResidencyType::Tenant);

        vote(openBallot($community), $unit, companyAdmin($community->company));
    })->throws(AuthorizationException::class, 'not eligible');

    it('refuses an owner voting for a unit they do not own', function () {
        $community = Community::factory()->create();
        [, $owner] = votingUnit($community);
        [$neighbour] = votingUnit($community);

        vote(openBallot($community), $neighbour, $owner);
    })->throws(AuthorizationException::class, 'Only an owner');

    it('refuses a unit from another community', function () {
        $community = Community::factory()->create();
        [$unit, $owner] = votingUnit(Community::factory()->for($community->company)->create());

        vote(openBallot($community), $unit, $owner);
    })->throws(AuthorizationException::class, 'not eligible');
});

describe('one vote per unit', function () {
    it('takes only the first vote from a unit with two owners', function () {
        $community = Community::factory()->create();
        [$unit, $first] = votingUnit($community);
        $coOwner = Resident::factory()->for($community->company)->withLogin()->create();
        Residency::factory()->for($unit)->for($coOwner)->create(['type' => ResidencyType::Owner, 'is_primary' => false]);
        $ballot = openBallot($community);

        vote($ballot, $unit, $first, 'Yes');

        expect(fn () => vote($ballot, $unit, $coOwner->user, 'No'))->toThrow(ValidationException::class, 'already been cast')
            ->and(fn () => vote($ballot, $unit, $first, 'No'))->toThrow(ValidationException::class, 'already been cast')
            ->and(BallotVote::count())->toBe(1);
    });

    it('never lets a vote be changed or deleted', function () {
        $community = Community::factory()->create();
        [$unit, $owner] = votingUnit($community);
        $vote = vote(openBallot($community), $unit, $owner);

        expect(fn () => $vote->forceFill(['weight' => '99'])->save())->toThrow(LogicException::class, 'append-only')
            ->and(fn () => $vote->delete())->toThrow(LogicException::class, 'append-only');
    });
});

describe('proxies', function () {
    it('lets the appointed proxy vote for the unit, once', function () {
        $community = Community::factory()->create();
        [$unit, $owner] = votingUnit($community);
        [$ownUnit, $holder] = votingUnit($community);
        $ballot = openBallot($community);

        $proxy = app(GrantProxy::class)->handle($ballot, $unit, $owner, $holder);
        $vote = vote($ballot, $unit, $holder, 'No');

        expect($vote->ballot_proxy_id)->toBe($proxy->id)
            ->and(fn () => vote($ballot, $unit, $holder, 'Yes'))->toThrow(ValidationException::class, 'already been cast')
            ->and(fn () => vote($ballot, $unit, $owner, 'Yes'))->toThrow(ValidationException::class, 'already been cast');

        // The holder still votes for their own unit separately.
        expect(vote($ballot, $ownUnit, $holder)->ballot_proxy_id)->toBeNull();
    });

    it('stops a replaced or revoked proxy from voting', function () {
        $community = Community::factory()->create();
        [$unit, $owner] = votingUnit($community);
        [, $first] = votingUnit($community);
        [, $second] = votingUnit($community);
        $ballot = openBallot($community);

        app(GrantProxy::class)->handle($ballot, $unit, $owner, $first);
        $replacement = app(GrantProxy::class)->handle($ballot, $unit, $owner, $second);

        expect(fn () => vote($ballot, $unit, $first))->toThrow(AuthorizationException::class);

        app(RevokeProxy::class)->handle($replacement, $owner);

        expect(fn () => vote($ballot, $unit, $second))->toThrow(AuthorizationException::class)
            ->and(vote($ballot, $unit, $owner)->ballot_proxy_id)->toBeNull();
    });

    it('cannot be appointed after the unit voted, or revoked after it was used', function () {
        $community = Community::factory()->create();
        [$unit, $owner] = votingUnit($community);
        [$other, $otherOwner] = votingUnit($community);
        [, $holder] = votingUnit($community);
        $ballot = openBallot($community);

        vote($ballot, $unit, $owner);
        expect(fn () => app(GrantProxy::class)->handle($ballot, $unit, $owner, $holder))->toThrow(ValidationException::class, 'already voted');

        $proxy = app(GrantProxy::class)->handle($ballot, $other, $otherOwner, $holder);
        vote($ballot, $other, $holder);
        expect(fn () => app(RevokeProxy::class)->handle($proxy, $otherOwner))->toThrow(ValidationException::class, 'already voted');
    });

    it('refuses proxies that are not the owner\'s to give, or to someone outside the community', function (string $case) {
        $community = Community::factory()->create();
        [$unit, $owner] = votingUnit($community);
        [, $holder] = votingUnit($community);
        $ballot = openBallot($community);

        $attempt = match ($case) {
            'tenant appoints' => fn () => app(GrantProxy::class)->handle($ballot, $unit, votingUnit($community, type: ResidencyType::Tenant)[1], $holder),
            'self' => fn () => app(GrantProxy::class)->handle($ballot, $unit, $owner, $owner),
            'another company' => fn () => app(GrantProxy::class)->handle($ballot, $unit, $owner, User::factory()->create()),
            'another community' => fn () => app(GrantProxy::class)->handle($ballot, $unit, $owner, votingUnit(Community::factory()->for($community->company)->create())[1]),
            'after voting ends' => function () use ($ballot, $unit, $owner, $holder) {
                travelTo(CarbonImmutable::parse('2026-10-20 12:00'));

                return app(GrantProxy::class)->handle($ballot, $unit, $owner, $holder);
            },
        };

        expect($attempt)->toThrow($case === 'tenant appoints' ? AuthorizationException::class : ValidationException::class);
    })->with(['tenant appoints', 'self', 'another company', 'another community', 'after voting ends']);

    it('accepts a board member or manager of the community as proxy', function () {
        $community = Community::factory()->create();
        [$unit, $owner] = votingUnit($community);
        $board = teamMember(CompanyRole::BoardMember, $community->company, [$community]);
        $ballot = openBallot($community);

        app(GrantProxy::class)->handle($ballot, $unit, $owner, $board);

        expect(vote($ballot, $unit, $board)->ballot_proxy_id)->not->toBeNull();
    });
});

describe('voting window', function () {
    it('only accepts votes while the ballot is open', function () {
        $community = Community::factory()->create();
        [$unit, $owner] = votingUnit($community);
        $ballot = openBallot($community, ['opens_at' => '2026-10-11 09:00', 'closes_at' => '2026-10-12 17:00']);

        expect($ballot->status())->toBe(BallotStatus::Upcoming)
            ->and(fn () => vote($ballot, $unit, $owner))->toThrow(ValidationException::class, 'not open');

        travelTo(CarbonImmutable::parse('2026-10-12 16:59'));
        expect(vote($ballot, $unit, $owner))->toBeInstanceOf(BallotVote::class);

        [$late, $lateOwner] = votingUnit($community);
        travelTo(CarbonImmutable::parse('2026-10-12 17:00'));
        expect($ballot->fresh()?->status())->toBe(BallotStatus::Ended)
            ->and(fn () => vote($ballot, $late, $lateOwner))->toThrow(ValidationException::class, 'not open');
    });

    it('refuses votes on a draft ballot', function () {
        $community = Community::factory()->create();
        [$unit, $owner] = votingUnit($community);

        vote(Ballot::factory()->for($community)->withQuestion()->create(), $unit, $owner);
    })->throws(ValidationException::class, 'not open');

    it('requires one valid answer for every question, and nothing else', function (string $case) {
        $community = Community::factory()->create();
        [$unit, $owner] = votingUnit($community);
        $ballot = Ballot::factory()->for($community)->open()->withQuestion('Approve budget?')->withQuestion('Elect Pat?', ['For', 'Against'])->create();
        $other = openBallot($community);
        [$budget, $election] = $ballot->questions()->get()->all();

        $choices = match ($case) {
            'missing question' => [$budget->id => option($ballot, 'Yes')],
            'option from the other question' => [$budget->id => option($ballot, 'For', 1), $election->id => option($ballot, 'For', 1)],
            'extra question' => [$budget->id => option($ballot, 'Yes'), $election->id => option($ballot, 'For', 1), $other->questions()->value('id') => option($other, 'Yes')],
        };

        expect(fn () => app(CastVote::class)->handle($ballot, $unit, $owner, $choices))->toThrow(ValidationException::class)
            ->and(BallotVote::count())->toBe(0);
    })->with(['missing question', 'option from the other question', 'extra question']);
});

/**
 * Four units (factors 10, 20, 30, 40): A (10) and C (30) vote Yes, B (20) votes No, D abstains
 * by not voting. By factor: Yes 40, No 20 of 60 cast → 66.67% / 33.33%, turnout 60 of 100.
 * One unit, one vote: Yes 2, No 1 of 3 → 66.67% / 33.33%, turnout 3 of 4 = 75%.
 */
function votingScenario(array $ballotAttributes): Ballot
{
    $community = Community::factory()->create();
    $units = collect(['10', '20', '30', '40'])->map(fn ($factor) => votingUnit($community, $factor));
    votingUnit($community, '50', type: ResidencyType::Tenant); // rented out with no owner on file: not on the roll
    $ballot = openBallot($community, $ballotAttributes);

    vote($ballot, $units[0][0], $units[0][1], 'Yes');
    vote($ballot, $units[1][0], $units[1][1], 'No');
    vote($ballot, $units[2][0], $units[2][1], 'Yes');

    travelTo(CarbonImmutable::parse('2026-10-20 12:00'));

    return app(CloseBallot::class)->handle($ballot);
}

describe('results', function () {
    it('weights by unit factor', function () {
        $results = votingScenario(['quorum_percent' => 50])->results;

        expect($results)->eligible_units->toBe(4)->voted_units->toBe(3)
            ->eligible_weight->toBe('100.000000')->voted_weight->toBe('60.000000')
            ->turnout_percent->toBe('60.00')->quorum_met->toBeTrue()
            ->and(collect($results['questions'][0]['options'])->map(fn ($o) => [$o['label'], $o['votes'], $o['weight'], $o['percent']])->all())
            ->toBe([['Yes', 2, '40.000000', '66.67'], ['No', 1, '20.000000', '33.33'], ['Abstain', 0, '0', '0.00']]);
    });

    it('counts one unit, one vote', function () {
        $results = votingScenario(['weighting' => VotingWeighting::PerUnit, 'quorum_percent' => 75])->results;

        expect($results)->voted_weight->toBe('3.000000')->eligible_weight->toBe('4.000000')
            ->turnout_percent->toBe('75.00')->quorum_met->toBeTrue()
            ->and(collect($results['questions'][0]['options'])->pluck('percent')->all())->toBe(['66.67', '33.33', '0.00']);
    });

    it('reports quorum not met when turnout falls short, even by a hair', function () {
        expect(votingScenario(['quorum_percent' => 61])->results['quorum_met'])->toBeFalse();
    });

    it('handles a ballot nobody voted on', function () {
        $community = Community::factory()->create();
        votingUnit($community);
        $ballot = openBallot($community);
        travelTo(CarbonImmutable::parse('2026-10-20 12:00'));

        $results = app(CloseBallot::class)->handle($ballot)->results;

        expect($results)->voted_units->toBe(0)->turnout_percent->toBe('0.00')->quorum_met->toBeFalse()
            ->and(collect($results['questions'][0]['options'])->pluck('percent')->unique()->all())->toBe(['0.00']);
    });
});

describe('closing', function () {
    it('refuses to close early and cannot be cut short', function () {
        $community = Community::factory()->create();

        app(CloseBallot::class)->handle(openBallot($community));
    })->throws(LogicException::class, 'voting time is over');

    it('freezes the results: later changes to units or owners do not move them', function () {
        $community = Community::factory()->create();
        [$unit, $owner] = votingUnit($community, '40');
        [$other] = votingUnit($community, '60');
        $ballot = openBallot($community);
        vote($ballot, $unit, $owner);
        travelTo(CarbonImmutable::parse('2026-10-20 12:00'));
        $closed = app(CloseBallot::class)->handle($ballot);
        $frozen = $closed->results;

        $unit->update(['unit_factor' => '5']);
        Residency::where('unit_id', $other->id)->update(['moved_out_on' => '2026-10-19']);
        app(CloseBallot::class)->handle($closed);

        expect($closed->fresh()?->results)->toEqual($frozen)
            ->and($frozen['turnout_percent'])->toBe('40.00')
            ->and(fn () => vote($closed, $unit, $owner))->toThrow(ValidationException::class, 'not open');
    });

    it('counts a unit that voted and then lost its owner in the denominator', function () {
        $community = Community::factory()->create();
        [$unit, $owner] = votingUnit($community, '50');
        votingUnit($community, '50');
        $ballot = openBallot($community);
        vote($ballot, $unit, $owner);
        Residency::where('unit_id', $unit->id)->update(['moved_out_on' => '2026-10-11']);
        travelTo(CarbonImmutable::parse('2026-10-20 12:00'));

        expect(app(CloseBallot::class)->handle($ballot)->results)->eligible_units->toBe(2)->turnout_percent->toBe('50.00');
    });

    it('is closed automatically by the scheduler once voting ends', function () {
        $community = Community::factory()->create();
        $ended = openBallot($community, ['closes_at' => '2026-10-10 11:00']);
        $running = openBallot($community);
        $draft = Ballot::factory()->for($community)->withQuestion()->create(['closes_at' => '2026-10-10 11:00']);

        artisan('ballots:close-ended')->expectsOutputToContain('Closed 1 ballot(s).')->assertSuccessful();

        expect($ended->fresh()?->status())->toBe(BallotStatus::Closed)
            ->and($running->fresh()?->status())->toBe(BallotStatus::Open)
            ->and($draft->fresh()?->status())->toBe(BallotStatus::Draft);
    });
});

describe('publishing', function () {
    it('publishes a complete ballot and refuses an incomplete one', function (string $case) {
        $community = Community::factory()->create();
        $admin = companyAdmin($community->company);
        $ballot = match ($case) {
            'complete' => Ballot::factory()->for($community)->withQuestion()->create(),
            'no questions' => Ballot::factory()->for($community)->create(),
            'one option' => Ballot::factory()->for($community)->withQuestion('Only one?', ['Yes'])->create(),
            'closes before it opens' => Ballot::factory()->for($community)->withQuestion()->create(['opens_at' => '2026-10-15', 'closes_at' => '2026-10-14']),
        };

        if ($case === 'complete') {
            app(PublishBallot::class)->handle($ballot, $admin);
            expect($ballot->fresh()?->status())->toBe(BallotStatus::Open);
        } else {
            expect(fn () => app(PublishBallot::class)->handle($ballot, $admin))->toThrow(LogicException::class);
        }
    })->with(['complete', 'no questions', 'one option', 'closes before it opens']);
});

it('keeps an audit trail of every vote, without recording how anyone voted', function () {
    $community = Community::factory()->create();
    [$unit, $owner] = votingUnit($community);
    [, $holder] = votingUnit($community);
    $ballot = openBallot($community);

    app(GrantProxy::class)->handle($ballot, $unit, $owner, $holder);
    vote($ballot, $unit, $holder, 'No');

    $log = Activity::where('log_name', 'ballots')->where('subject_id', $ballot->id)->orderBy('id')->get();

    expect($log->pluck('description')->all())->toBe(['created', 'proxy appointed', 'vote cast'])
        ->and($log[2]->causer_id)->toBe($holder->id)
        ->and($log[2]->properties->all())->toEqual(['unit_id' => $unit->id, 'unit' => $unit->number, 'via_proxy' => true])
        ->and(json_encode($log->pluck('properties')))->not->toContain('No');
});

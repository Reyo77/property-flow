<?php

use App\Actions\Engagement\ForumActions;
use App\Actions\Engagement\SaveSurvey;
use App\Actions\Engagement\SignConsentForm;
use App\Actions\Engagement\SubmitSurveyResponse;
use App\Enums\Audience;
use App\Enums\ForumTopicKind;
use App\Enums\ResidencyType;
use App\Models\Community;
use App\Models\ConsentForm;
use App\Models\ConsentSignature;
use App\Models\ContentReport;
use App\Models\ForumTopic;
use App\Models\Residency;
use App\Models\Resident;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\Unit;
use App\Models\User;
use App\Support\Governance\SurveyResults;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => Storage::fake('local'));

function communityMember(Community $community, ResidencyType $type = ResidencyType::Owner): User
{
    $resident = Resident::factory()->for($community->company)->withLogin()->create();
    Residency::factory()->for(Unit::factory()->for($community))->for($resident)->create(['type' => $type]);

    return $resident->user;
}

/**
 * A published survey: Q1 single choice (Pool, Gym, Party room), Q2 several (Mon, Wed, Fri, optional),
 * Q3 a rating, Q4 free text (optional).
 */
function sampleSurvey(Community $community, array $attributes = []): Survey
{
    $saver = app(SaveSurvey::class);
    $survey = $saver->handle($community, null, [
        'title' => 'Amenities survey', 'description' => null, 'is_poll' => false,
        'audience' => 'residents', 'is_anonymous' => false, 'closes_at' => null, ...$attributes,
    ], [
        ['kind' => 'single_choice', 'title' => 'Favourite amenity?', 'is_required' => true, 'options' => ['Pool', 'Gym', 'Party room']],
        ['kind' => 'multiple_choice', 'title' => 'Which days do you use it?', 'is_required' => false, 'options' => ['Mon', 'Wed', 'Fri']],
        ['kind' => 'rating', 'title' => 'How clean is it?', 'is_required' => true, 'options' => []],
        ['kind' => 'text', 'title' => 'Anything else?', 'is_required' => false, 'options' => []],
    ], companyAdmin($community->company));
    $saver->publish($survey);

    return $survey->fresh() ?? $survey;
}

/**
 * @return array<int, int|string|list<int>|null>
 */
function surveyAnswers(Survey $survey, string $favourite, array $days, ?int $rating, ?string $text = null): array
{
    [$q1, $q2, $q3, $q4] = $survey->questions()->with('options')->get()->all();

    return [
        $q1->id => $q1->options->firstWhere('label', $favourite)?->id,
        $q2->id => $q2->options->whereIn('label', $days)->pluck('id')->all(),
        $q3->id => $rating,
        $q4->id => $text,
    ];
}

describe('surveys', function () {
    it('records answers and tallies them', function () {
        $community = Community::factory()->create();
        $survey = sampleSurvey($community);

        app(SubmitSurveyResponse::class)->handle($survey, communityMember($community), surveyAnswers($survey, 'Pool', ['Mon', 'Fri'], 5, 'More loungers please'));
        app(SubmitSurveyResponse::class)->handle($survey, communityMember($community, ResidencyType::Tenant), surveyAnswers($survey, 'Gym', ['Mon'], 3));
        app(SubmitSurveyResponse::class)->handle($survey, communityMember($community), surveyAnswers($survey, 'Pool', [], 4));

        $results = app(SurveyResults::class)->for($survey);
        [$favourite, $days, $clean, $other] = $results['questions'];

        expect($results['responses'])->toBe(3)
            ->and(collect($favourite['options'])->map(fn ($o) => [$o['label'], $o['count'], $o['percent']])->all())->toBe([['Pool', 2, '66.7'], ['Gym', 1, '33.3'], ['Party room', 0, '0.0']])
            ->and(collect($days['options'])->pluck('count', 'label')->all())->toBe(['Mon' => 2, 'Wed' => 0, 'Fri' => 1])
            ->and($clean['average'])->toBe('4.0')
            ->and($clean['ratings'])->toBe([1 => 0, 2 => 0, 3 => 1, 4 => 1, 5 => 1])
            ->and($other['texts'])->toHaveCount(1)
            ->and($other['texts'][0]['name'])->not->toBeNull();
    });

    it('hides who wrote what on an anonymous survey', function () {
        $community = Community::factory()->create();
        $survey = sampleSurvey($community, ['is_anonymous' => true]);

        app(SubmitSurveyResponse::class)->handle($survey, communityMember($community), surveyAnswers($survey, 'Pool', [], 5, 'Honest feedback'));

        expect(app(SurveyResults::class)->for($survey)['questions'][3]['texts'])->toBe([['text' => 'Honest feedback', 'name' => null]]);
    });

    it('takes one response per person', function () {
        $community = Community::factory()->create();
        $survey = sampleSurvey($community);
        $member = communityMember($community);
        app(SubmitSurveyResponse::class)->handle($survey, $member, surveyAnswers($survey, 'Pool', [], 5));

        expect(fn () => app(SubmitSurveyResponse::class)->handle($survey, $member, surveyAnswers($survey, 'Gym', [], 1)))
            ->toThrow(ValidationException::class, 'already answered');
        expect(SurveyResponse::count())->toBe(1);
    });

    it('validates each answer against its question', function (string $case, string $error) {
        $community = Community::factory()->create();
        $survey = sampleSurvey($community);
        $other = sampleSurvey($community);
        $answers = surveyAnswers($survey, 'Pool', ['Mon'], 4);
        [$q1, $q2, $q3] = $survey->questions()->get()->all();

        match ($case) {
            'missing required' => $answers[$q1->id] = null,
            'option from another survey' => $answers[$q1->id] = $other->questions()->first()?->options()->value('id'),
            'rating out of range' => $answers[$q3->id] = 6,
            'rating not a whole number' => $answers[$q3->id] = '4.5',
            'several with a foreign option' => $answers[$q2->id] = [999999],
        };

        expect(fn () => app(SubmitSurveyResponse::class)->handle($survey, communityMember($community), $answers))->toThrow(ValidationException::class, $error);
        expect(SurveyResponse::count())->toBe(0);
    })->with([
        ['missing required', 'Please answer'],
        ['option from another survey', 'Choose one of the options'],
        ['rating out of range', 'rating from 1 to 5'],
        ['rating not a whole number', 'rating from 1 to 5'],
        ['several with a foreign option', 'Choose from the options given'],
    ]);

    it('respects the audience and the closing time', function () {
        $community = Community::factory()->create();
        $ownersOnly = sampleSurvey($community, ['audience' => 'owners']);
        $closed = sampleSurvey($community, ['closes_at' => now()->subMinute()->toDateTimeString()]);

        expect(fn () => app(SubmitSurveyResponse::class)->handle($ownersOnly, communityMember($community, ResidencyType::Tenant), surveyAnswers($ownersOnly, 'Pool', [], 3)))->toThrow(AuthorizationException::class)
            ->and(fn () => app(SubmitSurveyResponse::class)->handle($ownersOnly, communityMember(Community::factory()->for($community->company)->create()), surveyAnswers($ownersOnly, 'Pool', [], 3)))->toThrow(AuthorizationException::class)
            ->and(fn () => app(SubmitSurveyResponse::class)->handle($closed, communityMember($community), surveyAnswers($closed, 'Pool', [], 3)))->toThrow(ValidationException::class, 'not open');
    });

    it('allows exactly one question in a poll, and fixes the questions once published', function () {
        $community = Community::factory()->create();
        $admin = companyAdmin($community->company);

        expect(fn () => app(SaveSurvey::class)->handle($community, null, ['title' => 'Poll', 'description' => null, 'is_poll' => true, 'audience' => 'residents', 'is_anonymous' => false, 'closes_at' => null], [], $admin))
            ->toThrow(LogicException::class, 'exactly one question')
            ->and(fn () => app(SaveSurvey::class)->handle($community, sampleSurvey($community), ['title' => 'X', 'description' => null, 'is_poll' => false, 'audience' => 'residents', 'is_anonymous' => false, 'closes_at' => null], [], $admin))
            ->toThrow(LogicException::class, 'no longer be edited');
    });
});

describe('consent forms', function () {
    function signature(): string
    {
        $image = imagecreatetruecolor(10, 10);
        ob_start();
        imagepng($image);

        return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
    }

    it('records a signature with the signed text\'s fingerprint, once per person', function () {
        $community = Community::factory()->create();
        $form = ConsentForm::factory()->for($community)->create(['audience' => Audience::Residents]);
        $member = communityMember($community);

        $signed = app(SignConsentForm::class)->handle($form, $member, 'Rita Resident', signature(), '203.0.113.9', 'Test browser');

        expect($signed)->signed_name->toBe('Rita Resident')->body_hash->toBe($form->bodyHash())->ip_address->toBe('203.0.113.9')
            ->and(fn () => app(SignConsentForm::class)->handle($form, $member, 'Rita', signature(), null, null))->toThrow(ValidationException::class, 'already signed')
            ->and(fn () => $signed->forceFill(['signed_name' => 'Someone else'])->save())->toThrow(LogicException::class, 'append-only');
        Storage::disk('local')->assertExists($signed->signature_disk_path);
    });

    it('detects that a form changed after it was signed', function () {
        $community = Community::factory()->create();
        $form = ConsentForm::factory()->for($community)->create(['audience' => Audience::Residents]);
        $signed = app(SignConsentForm::class)->handle($form, communityMember($community), 'Pat', signature(), null, null);

        $form->update(['body' => 'Quietly different terms.']);

        expect($signed->body_hash)->not->toBe($form->fresh()?->bodyHash());
    });

    it('refuses a missing signature, a blank name, the wrong audience, or a closed form', function (string $case) {
        $community = Community::factory()->create();
        $form = ConsentForm::factory()->for($community)->create(['audience' => Audience::Owners]);
        $owner = communityMember($community);

        $attempt = match ($case) {
            'no drawing' => fn () => app(SignConsentForm::class)->handle($form, $owner, 'Pat', 'data:image/png;base64,bm90IGFuIGltYWdl', null, null),
            'blank name' => fn () => app(SignConsentForm::class)->handle($form, $owner, '   ', signature(), null, null),
            'tenant' => fn () => app(SignConsentForm::class)->handle($form, communityMember($community, ResidencyType::Tenant), 'Pat', signature(), null, null),
            'closed' => fn () => app(SignConsentForm::class)->handle(tap($form)->update(['closes_at' => now()->subDay()]), $owner, 'Pat', signature(), null, null),
        };

        expect($attempt)->toThrow($case === 'tenant' ? AuthorizationException::class : ValidationException::class);
        expect(ConsentSignature::count())->toBe(0);
    })->with(['no drawing', 'blank name', 'tenant', 'closed']);
});

describe('forum', function () {
    it('lets residents post and reply, keeps activity current, and blocks replies to locked topics', function () {
        $community = Community::factory()->create();
        $author = communityMember($community);
        $forum = app(ForumActions::class);
        $topic = $forum->postTopic($community, $author, ForumTopicKind::Discussion, 'Bike room', 'Anyone else find it full?', 999);

        $this->travel(2)->hours();
        $forum->reply($topic, communityMember($community), 'Yes, every evening.');

        expect($topic->fresh())->price_cents->toBeNull()->last_activity_at->greaterThan($topic->created_at)->toBeTrue();

        $forum->setLocked($topic, true);

        expect(fn () => $forum->reply($topic->fresh() ?? $topic, $author, 'One more'))->toThrow(ValidationException::class, 'closed to new replies');
    });

    it('keeps a price only on for-sale listings, and lets the seller mark one sold', function () {
        $community = Community::factory()->create();
        $seller = communityMember($community);
        $forum = app(ForumActions::class);

        $sale = $forum->postTopic($community, $seller, ForumTopicKind::ForSale, 'Bike', 'Barely used', 15000);
        $free = $forum->postTopic($community, $seller, ForumTopicKind::Free, 'Boxes', 'Moving boxes', 500);
        $forum->closeListing($sale);

        expect($sale->fresh())->price_cents->toBe(15000)->closed_at->not->toBeNull()
            ->and($free->price_cents)->toBeNull()
            ->and(fn () => $forum->reply($sale->fresh() ?? $sale, communityMember($community), 'Still available?'))->toThrow(ValidationException::class);
    });

    it('takes one report per person, and resolves reports when a moderator hides the post', function () {
        $community = Community::factory()->create();
        $forum = app(ForumActions::class);
        $topic = ForumTopic::factory()->for($community)->create();
        $reporter = communityMember($community);

        $forum->report($topic, $reporter, 'Spam');
        $forum->report($topic, communityMember($community), 'Rude');

        expect(fn () => $forum->report($topic, $reporter, 'Again'))->toThrow(ValidationException::class, 'already reported');

        $forum->setHidden($topic, true, companyAdmin($community->company));

        expect($topic->fresh()?->isHidden())->toBeTrue()
            ->and(ContentReport::whereNull('resolved_at')->count())->toBe(0);

        $forum->setHidden($topic, false, companyAdmin($community->company));

        expect($topic->fresh()?->isHidden())->toBeFalse();
    });
});

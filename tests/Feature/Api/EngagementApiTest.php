<?php

use App\Enums\Audience;
use App\Enums\CompanyRole;
use App\Enums\ResidencyType;
use App\Models\ArchitecturalRequest;
use App\Models\Community;
use App\Models\ConsentForm;
use App\Models\ConsentSignature;
use App\Models\ContentReport;
use App\Models\ForumTopic;
use App\Models\Survey;
use App\Models\SurveyOption;
use App\Models\SurveyQuestion;
use App\Models\Unit;
use App\Models\ViolationRule;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
});

function pngDataUrl(): string
{
    $image = imagecreatetruecolor(10, 10);
    ob_start();
    imagepng($image);

    return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
}

describe('violations', function () {
    it('lets staff report one, and shows it with its letter to the unit\'s owner only', function () {
        $community = Community::factory()->create();
        $rule = ViolationRule::factory()->for($community)->create();
        $owner = residentOf($community, ['type' => ResidencyType::Owner]);
        $unit = $owner->residencies()->sole()->unit;
        Sanctum::actingAs(teamMember(CompanyRole::Staff, $community->company, [$community]));

        $id = postJson(route('api.v1.communities.violations.store', $community), [
            'violation_rule_id' => $rule->id, 'unit_id' => $unit->id, 'description' => 'Dog off leash',
        ])->assertCreated()->assertJsonPath('data.stage', 'courtesy')->json('data.id');

        Sanctum::actingAs($owner->user);
        $letter = getJson(route('api.v1.communities.violations.show', [$community, $id]))->assertOk()->json('data.notices.0.letter_url');
        get($letter)->assertOk()->assertHeader('content-type', 'application/pdf');

        Sanctum::actingAs(residentOf($community, ['type' => ResidencyType::Owner])->user);
        getJson(route('api.v1.communities.violations.index', $community))->assertJsonCount(0, 'data');
        getJson(route('api.v1.communities.violations.show', [$community, $id]))->assertForbidden();
        get($letter)->assertForbidden();
        postJson(route('api.v1.communities.violations.store', $community), ['violation_rule_id' => $rule->id, 'unit_id' => $unit->id, 'description' => 'x'])->assertForbidden();
    });

    it('validates a report', function () {
        $community = Community::factory()->create();
        Sanctum::actingAs(companyAdmin($community->company));

        postJson(route('api.v1.communities.violations.store', $community), [
            'violation_rule_id' => ViolationRule::factory()->create()->id, 'unit_id' => Unit::factory()->create()->id, 'observed_at' => now()->addDay()->toIso8601String(),
        ])->assertUnprocessable()->assertJsonValidationErrors(['violation_rule_id', 'unit_id', 'description', 'observed_at']);
    });
});

describe('renovation requests', function () {
    it('lets an owner ask, the board decide, and the owner download the letter', function () {
        $community = Community::factory()->create();
        $owner = residentOf($community, ['type' => ResidencyType::Owner]);
        $unit = $owner->residencies()->sole()->unit;
        Sanctum::actingAs($owner->user);

        $id = postJson(route('api.v1.communities.architectural-requests.store', $community), [
            'unit_id' => $unit->id, 'title' => 'Hardwood floors', 'description' => 'Living room',
        ])->assertCreated()->assertJsonPath('data.status', 'submitted')->assertJsonPath('data.decision_letter_url', null)->json('data.id');

        postJson(route('api.v1.communities.architectural-requests.decision.store', [$community, $id]), ['decision' => 'approved'])->assertForbidden();

        Sanctum::actingAs(teamMember(CompanyRole::BoardMember, $community->company, [$community]));
        postJson(route('api.v1.communities.architectural-requests.decision.store', [$community, $id]), ['decision' => 'approved_with_conditions'])
            ->assertUnprocessable()->assertJsonValidationErrors('conditions');
        postJson(route('api.v1.communities.architectural-requests.decision.store', [$community, $id]), ['decision' => 'approved_with_conditions', 'conditions' => 'Acoustic underlay'])
            ->assertOk()->assertJsonPath('data.conditions', 'Acoustic underlay');
        postJson(route('api.v1.communities.architectural-requests.decision.store', [$community, $id]), ['decision' => 'denied', 'notes' => 'Changed our minds'])->assertForbidden();

        Sanctum::actingAs($owner->user);
        get(getJson(route('api.v1.communities.architectural-requests.show', [$community, $id]))->json('data.decision_letter_url'))->assertOk();
    });

    it('does not let a tenant ask, or an owner see a neighbour\'s request', function () {
        $community = Community::factory()->create();
        $tenant = residentOf($community, ['type' => ResidencyType::Tenant]);
        $neighbours = ArchitecturalRequest::factory()->for($community)->create();

        Sanctum::actingAs($tenant->user);
        postJson(route('api.v1.communities.architectural-requests.store', $community), [
            'unit_id' => $tenant->residencies()->value('unit_id'), 'title' => 'Paint', 'description' => 'x',
        ])->assertForbidden();

        Sanctum::actingAs(residentOf($community, ['type' => ResidencyType::Owner])->user);
        getJson(route('api.v1.communities.architectural-requests.show', [$community, $neighbours]))->assertForbidden();
    });
});

describe('surveys and forms', function () {
    it('takes one answer to a poll, then shows its results', function () {
        $community = Community::factory()->create();
        $poll = Survey::factory()->for($community)->create(['is_poll' => true]);
        $question = SurveyQuestion::factory()->for($poll)->create(['position' => 1]);
        $green = SurveyOption::factory()->for($question, 'question')->create(['label' => 'Sage green', 'position' => 1]);
        SurveyOption::factory()->for($question, 'question')->create(['label' => 'Warm grey', 'position' => 2]);
        Sanctum::actingAs(residentOf($community)->user);

        getJson(route('api.v1.communities.surveys.show', [$community, $poll]))->assertJsonPath('can_answer', true)->assertJsonPath('results', null);
        postJson(route('api.v1.communities.surveys.responses.store', [$community, $poll]), ['answers' => []])->assertUnprocessable();
        postJson(route('api.v1.communities.surveys.responses.store', [$community, $poll]), ['answers' => [$question->id => $green->id]])->assertCreated();
        postJson(route('api.v1.communities.surveys.responses.store', [$community, $poll]), ['answers' => [$question->id => $green->id]])->assertUnprocessable();

        getJson(route('api.v1.communities.surveys.show', [$community, $poll]))
            ->assertJsonPath('can_answer', false)
            ->assertJsonPath('answered', true)
            ->assertJsonPath('results.responses', 1);
    });

    it('lets an owner sign an owners\' form once, and hides it from tenants', function () {
        $community = Community::factory()->create();
        $form = ConsentForm::factory()->for($community)->create(['audience' => Audience::Owners]);
        $owner = residentOf($community, ['type' => ResidencyType::Owner]);

        Sanctum::actingAs(residentOf($community, ['type' => ResidencyType::Tenant])->user);
        getJson(route('api.v1.communities.consent-forms.index', $community))->assertJsonCount(0, 'data');
        getJson(route('api.v1.communities.consent-forms.show', [$community, $form]))->assertForbidden();

        Sanctum::actingAs($owner->user);
        postJson(route('api.v1.communities.consent-forms.signatures.store', [$community, $form]), ['signed_name' => 'Rita'])
            ->assertUnprocessable()->assertJsonValidationErrors(['signature', 'agreed']);
        postJson(route('api.v1.communities.consent-forms.signatures.store', [$community, $form]), ['signed_name' => 'Rita', 'signature' => pngDataUrl(), 'agreed' => true])->assertCreated();
        postJson(route('api.v1.communities.consent-forms.signatures.store', [$community, $form]), ['signed_name' => 'Rita', 'signature' => pngDataUrl(), 'agreed' => true])->assertUnprocessable();

        getJson(route('api.v1.communities.consent-forms.show', [$community, $form]))->assertJsonPath('signed_at', fn ($value) => $value !== null);
        expect(ConsentSignature::count())->toBe(1);
    });
});

describe('community board', function () {
    it('posts a listing, takes replies and reports, and refuses replies once locked', function () {
        $community = Community::factory()->create();
        $seller = residentOf($community);
        $neighbour = residentOf($community);
        Sanctum::actingAs($seller->user);

        $id = postJson(route('api.v1.communities.forum-topics.store', $community), ['kind' => 'for_sale', 'title' => 'Road bike', 'body' => 'Size M', 'price_cents' => 25000])
            ->assertCreated()->assertJsonPath('data.price_cents', 25000)->json('data.id');
        postJson(route('api.v1.communities.forum-topics.reports.store', [$community, $id]), ['reason' => 'Mine'])->assertUnprocessable();

        Sanctum::actingAs($neighbour->user);
        postJson(route('api.v1.communities.forum-topics.replies.store', [$community, $id]), ['body' => 'Still available?'])->assertCreated();
        postJson(route('api.v1.communities.forum-topics.reports.store', [$community, $id]), ['reason' => 'Looks stolen'])->assertCreated();
        postJson(route('api.v1.communities.forum-topics.reports.store', [$community, $id]), ['reason' => 'Again'])->assertUnprocessable();

        getJson(route('api.v1.communities.forum-topics.index', ['community' => $community, 'filter' => ['section' => 'classifieds']]))->assertJsonPath('data.*.id', [$id]);
        getJson(route('api.v1.communities.forum-topics.show', [$community, $id]))->assertJsonCount(1, 'data.replies');

        ForumTopic::findOrFail($id)->forceFill(['locked_at' => now()])->save();
        postJson(route('api.v1.communities.forum-topics.replies.store', [$community, $id]), ['body' => 'Hello?'])->assertUnprocessable();
        expect(ContentReport::count())->toBe(1);
    });

    it('hides hidden posts from residents', function () {
        $community = Community::factory()->create();
        $hidden = ForumTopic::factory()->for($community)->create(['hidden_at' => now()]);
        Sanctum::actingAs(residentOf($community)->user);

        getJson(route('api.v1.communities.forum-topics.index', $community))->assertJsonCount(0, 'data');
        getJson(route('api.v1.communities.forum-topics.show', [$community, $hidden]))->assertForbidden();
    });
});

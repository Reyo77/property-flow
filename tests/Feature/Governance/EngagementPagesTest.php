<?php

use App\Enums\Audience;
use App\Enums\CompanyRole;
use App\Enums\ForumTopicKind;
use App\Enums\ResidencyType;
use App\Livewire\Engagement\ConsentForms;
use App\Livewire\Engagement\ConsentFormShow;
use App\Livewire\Engagement\Forum;
use App\Livewire\Engagement\ForumTopicShow;
use App\Livewire\Engagement\SurveyForm;
use App\Livewire\Engagement\SurveyShow;
use App\Livewire\Governance\BoardPortal;
use App\Models\ArchitecturalRequest;
use App\Models\Community;
use App\Models\ConsentForm;
use App\Models\ConsentSignature;
use App\Models\ContentReport;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\Residency;
use App\Models\Resident;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\Unit;
use App\Models\User;
use App\Models\VendorBill;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(fn () => Storage::fake('local'));

function memberOf(Community $community, ResidencyType $type = ResidencyType::Owner): User
{
    $resident = Resident::factory()->for($community->company)->withLogin()->create();
    Residency::factory()->for(Unit::factory()->for($community))->for($resident)->create(['type' => $type]);

    return $resident->user;
}

function pngSignature(): string
{
    $image = imagecreatetruecolor(10, 10);
    ob_start();
    imagepng($image);

    return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
}

describe('surveys', function () {
    it('builds a poll, publishes it, and shows voters the results only after they answer', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        actingAs($admin);

        Livewire::test(SurveyForm::class, ['community' => $community])
            ->set('title', 'Paint colour for the lobby')
            ->set('is_poll', true)
            ->set('questions.0.title', 'Which colour?')
            ->set('questions.0.options.0', 'Warm grey')
            ->set('questions.0.options.1', 'Sage green')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $poll = Survey::sole();
        Livewire::test(SurveyShow::class, ['community' => $community, 'survey' => $poll])->call('publish');
        $question = $poll->questions()->sole();

        actingAs(memberOf($community));
        Livewire::test(SurveyShow::class, ['community' => $community, 'survey' => $poll->fresh()])
            ->assertDontSee('data-test="results"', false)
            ->set("answers.{$question->id}", (string) $question->options()->where('label', 'Sage green')->value('id'))
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSee('data-test="results"', false)
            ->assertSee('100.0%');

        expect(SurveyResponse::count())->toBe(1);
    });

    it('keeps survey results from residents, and drafts from everyone but staff', function () {
        $community = Community::factory()->create();
        $survey = Survey::factory()->for($community)->create();
        $draft = Survey::factory()->for($community)->create(['published_at' => null]);
        $member = memberOf($community);
        SurveyResponse::factory()->for($survey)->create(['user_id' => $member->id]);
        actingAs($member);

        Livewire::test(SurveyShow::class, ['community' => $community, 'survey' => $survey])->assertDontSee('data-test="results"', false);
        get(route('communities.surveys.show', [$community, $draft]))->assertForbidden();
        get(route('communities.surveys.create', $community))->assertForbidden();
        get(route('communities.surveys.edit', [$community, $draft]))->assertForbidden();
    });

    it('edits a draft, and refuses to edit once published', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $draft = Survey::factory()->for($community)->create(['published_at' => null, 'title' => 'Gym hours']);
        SurveyQuestion::factory()->for($draft)->create(['position' => 1, 'kind' => 'text', 'title' => 'Anything else?', 'is_required' => false]);
        actingAs($admin);

        Livewire::test(SurveyForm::class, ['community' => $community, 'survey' => $draft])
            ->assertSet('title', 'Gym hours')
            ->assertSet('questions.0.title', 'Anything else?')
            ->set('title', 'Gym opening hours')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('communities.surveys.show', [$community, $draft]));

        expect(Survey::sole()->title)->toBe('Gym opening hours')->and($draft->questions()->sole()->title)->toBe('Anything else?');

        $draft->forceFill(['published_at' => now()])->save();
        get(route('communities.surveys.edit', [$community, $draft]))->assertNotFound();
    });
});

describe('consent forms', function () {
    it('lets an owner read and sign, and staff see who signed', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $form = ConsentForm::factory()->for($community)->create(['audience' => Audience::Owners]);
        $owner = memberOf($community);

        actingAs($owner);
        Livewire::test(ConsentFormShow::class, ['community' => $community, 'consentForm' => $form])
            ->set('signed_name', 'Rita Resident')
            ->set('signature', pngSignature())
            ->call('sign')
            ->assertHasErrors('agreed')
            ->set('agreed', true)
            ->call('sign')
            ->assertHasNoErrors()
            ->assertSee('You signed this');

        $signature = ConsentSignature::sole();
        get(route('communities.consent-forms.signature', [$community, $form, $signature]))->assertOk();

        actingAs(memberOf($community));
        get(route('communities.consent-forms.signature', [$community, $form, $signature]))->assertForbidden();

        actingAs($admin);
        Livewire::test(ConsentFormShow::class, ['community' => $community, 'consentForm' => $form])->assertSee('Signed by 1 of 2')->assertSee('Rita Resident');
    });

    it('does not offer the form to tenants when it is for owners', function () {
        $community = Community::factory()->create();
        $form = ConsentForm::factory()->for($community)->create(['audience' => Audience::Owners]);
        actingAs(memberOf($community, ResidencyType::Tenant));

        get(route('communities.consent-forms.show', [$community, $form]))->assertForbidden();
        expect(Livewire::test(ConsentForms::class, ['community' => $community])->instance()->forms())->toBeEmpty();
    });
});

describe('community board', function () {
    it('lets a resident post a listing, a neighbour reply and report, and a moderator hide it', function () {
        $community = Community::factory()->create();
        $seller = memberOf($community, ResidencyType::Tenant);
        $neighbour = memberOf($community);

        actingAs($seller);
        Livewire::test(Forum::class, ['community' => $community])
            ->set('tab', 'classifieds')
            ->call('create')
            ->set('kind', 'for_sale')
            ->set('title', 'Road bike')
            ->set('body', 'Size M, barely used')
            ->set('price', '250')
            ->call('post')
            ->assertHasNoErrors()
            ->assertRedirect();

        $listing = ForumTopic::sole();
        expect($listing)->kind->toBe(ForumTopicKind::ForSale)->price_cents->toBe(25000);

        actingAs($neighbour);
        Livewire::test(ForumTopicShow::class, ['community' => $community, 'forumTopic' => $listing])
            ->set('reply', 'Is it still available?')
            ->call('postReply')
            ->call('startReport')
            ->set('reason', 'Looks like a scam')
            ->call('report')
            ->assertHasNoErrors();

        expect(ForumPost::count())->toBe(1)->and(ContentReport::count())->toBe(1);

        $moderator = teamMember(CompanyRole::PropertyManager, $community->company, [$community]);
        actingAs($moderator);
        Livewire::test(Forum::class, ['community' => $community])->assertSee('1 report to review');
        Livewire::test(ForumTopicShow::class, ['community' => $community, 'forumTopic' => $listing])->call('toggleHidden');

        expect($listing->fresh()?->isHidden())->toBeTrue()->and(ContentReport::whereNull('resolved_at')->count())->toBe(0);

        actingAs($neighbour);
        get(route('communities.forum.show', [$community, $listing]))->assertForbidden();
        expect(Livewire::test(Forum::class, ['community' => $community])->set('tab', 'classifieds')->instance()->topics()->total())->toBe(0);
    });

    it('keeps moderation tools from residents and the board closed to outsiders', function () {
        $community = Community::factory()->create();
        $topic = ForumTopic::factory()->for($community)->create();
        $resident = memberOf($community);

        actingAs($resident);
        Livewire::test(ForumTopicShow::class, ['community' => $community, 'forumTopic' => $topic])->call('toggleHidden')->assertForbidden();
        Livewire::test(ForumTopicShow::class, ['community' => $community, 'forumTopic' => $topic])->call('toggleLocked')->assertForbidden();

        actingAs(memberOf(Community::factory()->for($community->company)->create()));
        get(route('communities.forum.index', $community))->assertForbidden();
        get(route('communities.forum.show', [$community, $topic]))->assertForbidden();
    });

    it('returns 404 for another company\'s topic, survey or form', function () {
        $admin = companyAdmin();
        actingAs($admin);

        foreach ([ForumTopic::factory()->create(), Survey::factory()->create(), ConsentForm::factory()->create()] as $foreign) {
            $route = match (true) {
                $foreign instanceof ForumTopic => 'communities.forum.show',
                $foreign instanceof Survey => 'communities.surveys.show',
                default => 'communities.consent-forms.show',
            };

            get(route($route, [$foreign->community_id, $foreign->id]))->assertNotFound();
        }

        $foreignDraft = Survey::factory()->create(['published_at' => null]);
        get(route('communities.surveys.edit', [$foreignDraft->community_id, $foreignDraft->id]))->assertNotFound();
    });
});

describe('board portal', function () {
    it('gathers what is waiting on the board and the finances', function () {
        $community = Community::factory()->create(['bill_approval_limit_cents' => 100000]);
        VendorBill::factory()->for($community)->create(['amount_cents' => 250000, 'description' => 'Roof repair']);
        ArchitecturalRequest::factory()->for($community)->create(['title' => 'Hardwood floors']);
        actingAs(teamMember(CompanyRole::BoardMember, $community->company, [$community]));

        Livewire::test(BoardPortal::class, ['community' => $community])
            ->assertSee('2 items waiting on the board')
            ->assertSee('Roof repair')
            ->assertSee('Hardwood floors')
            ->assertSee('Cash in bank');
    });

    it('is for the board and managers only', function () {
        $community = Community::factory()->create();

        actingAs(teamMember(CompanyRole::Staff, $community->company, [$community]));
        get(route('communities.board', $community))->assertForbidden();

        actingAs(memberOf($community));
        get(route('communities.board', $community))->assertForbidden();
    });
});

<?php

use App\Actions\Violations\ReportViolation;
use App\Enums\ArchitecturalRequestStatus;
use App\Enums\CompanyRole;
use App\Enums\ResidencyType;
use App\Enums\ViolationStatus;
use App\Livewire\ArchitecturalRequests\Index as RequestsIndex;
use App\Livewire\ArchitecturalRequests\Show as RequestShow;
use App\Livewire\Violations\Index as ViolationsIndex;
use App\Livewire\Violations\Show as ViolationShow;
use App\Models\ArchitecturalRequest;
use App\Models\Community;
use App\Models\Residency;
use App\Models\Resident;
use App\Models\Unit;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationRule;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(fn () => Storage::fake('local'));

/**
 * @return array{0: Unit, 1: User}
 */
function residentUnit(Community $community, ResidencyType $type = ResidencyType::Owner): array
{
    $unit = Unit::factory()->for($community)->create();
    $resident = Resident::factory()->for($community->company)->withLogin()->create();
    Residency::factory()->for($unit)->for($resident)->create(['type' => $type]);

    return [$unit, $resident->user];
}

describe('violations', function () {
    it('lets staff report a violation with a photo, then a manager close it', function () {
        $community = Community::factory()->create();
        $rule = ViolationRule::factory()->for($community)->create();
        [$unit] = residentUnit($community);

        actingAs(teamMember(CompanyRole::Staff, $community->company, [$community]));
        Livewire::test(ViolationsIndex::class, ['community' => $community])
            ->call('create')
            ->set('violation_rule_id', (string) $rule->id)
            ->set('unit_id', (string) $unit->id)
            ->set('description', 'Dog off leash in the lobby')
            ->set('photos', [UploadedFile::fake()->image('dog.jpg')])
            ->call('report')
            ->assertHasNoErrors()
            ->assertRedirect();

        $violation = Violation::sole();
        expect($violation->notices()->count())->toBe(1)->and($violation->attachments()->count())->toBe(1);

        actingAs(teamMember(CompanyRole::PropertyManager, $community->company, [$community]));
        Livewire::test(ViolationShow::class, ['community' => $community, 'violation' => $violation])
            ->set('resolution_notes', 'Owner apologised')
            ->call('close', 'resolved');

        expect($violation->fresh()?->status)->toBe(ViolationStatus::Resolved);
    });

    it('lets staff report but not escalate, close or edit rules', function () {
        $community = Community::factory()->create();
        $violation = Violation::factory()->for(ViolationRule::factory()->for($community), 'rule')->create();
        actingAs(teamMember(CompanyRole::Staff, $community->company, [$community]));

        Livewire::test(ViolationShow::class, ['community' => $community, 'violation' => $violation])->call('escalate')->assertForbidden();
        Livewire::test(ViolationShow::class, ['community' => $community, 'violation' => $violation])->call('close', 'resolved')->assertForbidden();
        Livewire::test(ViolationsIndex::class, ['community' => $community])->call('editRule')->assertForbidden();
    });

    it('manages the rule library', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        actingAs($admin);

        Livewire::test(ViolationsIndex::class, ['community' => $community])
            ->call('editRule')
            ->set('rule_title', 'Barbecues on balconies')
            ->set('rule_fine', '150')
            ->set('rule_cure_days', 7)
            ->call('saveRule')
            ->assertHasNoErrors();

        expect(ViolationRule::sole())->fine_cents->toBe(15000)->cure_days->toBe(7);
    });

    it('shows owners only the notices for their own units, and tenants none', function () {
        $community = Community::factory()->create();
        $rule = ViolationRule::factory()->for($community)->create(['title' => 'Balcony storage']);
        [$unit, $owner] = residentUnit($community);
        [$neighbourUnit] = residentUnit($community);
        [, $tenant] = residentUnit($community, ResidencyType::Tenant);
        $mine = app(ReportViolation::class)->handle($rule, $unit, CarbonImmutable::now(), 'Boxes on balcony', null, [], companyAdmin($community->company));
        $theirs = app(ReportViolation::class)->handle($rule, $neighbourUnit, CarbonImmutable::now(), 'Bike on balcony', null, [], companyAdmin($community->company));

        actingAs($owner);
        Livewire::test(ViolationsIndex::class, ['community' => $community])->assertSee('Balcony storage')->assertDontSee('Report violation')->assertDontSee('Rules');
        expect(Livewire::test(ViolationsIndex::class, ['community' => $community])->instance()->violations()->pluck('id')->all())->toBe([$mine->id]);
        get(route('communities.violations.show', [$community, $mine]))->assertOk();
        get(route('communities.violations.show', [$community, $theirs]))->assertForbidden();
        get(route('communities.violations.notices.letter', [$community, $mine, $mine->notices()->sole()]))->assertOk()->assertHeader('content-type', 'application/pdf');
        get(route('communities.violations.notices.letter', [$community, $theirs, $theirs->notices()->sole()]))->assertForbidden();
        get(route('communities.violations.notices.letter', [$community, $mine, $theirs->notices()->sole()]))->assertNotFound();

        actingAs($tenant);
        get(route('communities.violations.show', [$community, $mine]))->assertForbidden();
    });

    it('returns 404 for another company\'s violation', function () {
        $admin = companyAdmin();
        $foreign = Violation::factory()->create();
        actingAs($admin);

        get(route('communities.violations.show', [$foreign->community_id, $foreign->id]))->assertNotFound();
    });
});

describe('renovation requests', function () {
    it('lets an owner submit with plans and the board decide, producing a decision letter', function () {
        $community = Community::factory()->create();
        [$unit, $owner] = residentUnit($community);
        $board = teamMember(CompanyRole::BoardMember, $community->company, [$community]);

        actingAs($owner);
        Livewire::test(RequestsIndex::class, ['community' => $community])
            ->call('create')
            ->set('title', 'Hardwood flooring')
            ->set('description', 'Replace carpet in the living room')
            ->set('plans', [UploadedFile::fake()->create('plan.pdf', 100, 'application/pdf')])
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect();

        $request = ArchitecturalRequest::sole();
        expect($request)->unit_id->toBe($unit->id)->submitted_by_id->toBe($owner->id);

        actingAs($board);
        Livewire::test(RequestShow::class, ['community' => $community, 'architecturalRequest' => $request])
            ->call('startReview')
            ->set('decision', 'approved_with_conditions')
            ->call('decide')
            ->assertHasErrors('conditions')
            ->set('conditions', 'Acoustic underlay required')
            ->call('decide')
            ->assertHasNoErrors();

        expect($request->fresh()?->status)->toBe(ArchitecturalRequestStatus::ApprovedWithConditions);

        actingAs($owner);
        get(route('communities.architectural-requests.letter', [$community, $request]))->assertOk()->assertHeader('content-type', 'application/pdf');
    });

    it('does not let tenants submit, or owners see other units\' requests', function () {
        $community = Community::factory()->create();
        [, $tenant] = residentUnit($community, ResidencyType::Tenant);
        [, $owner] = residentUnit($community);
        $other = ArchitecturalRequest::factory()->for($community)->create();

        actingAs($tenant);
        Livewire::test(RequestsIndex::class, ['community' => $community])->call('create')->assertForbidden();

        actingAs($owner);
        get(route('communities.architectural-requests.show', [$community, $other]))->assertForbidden();
        expect(Livewire::test(RequestsIndex::class, ['community' => $community])->instance()->requests())->toBeEmpty();
    });

    it('lets staff view but not decide, and hides the letter until decided', function () {
        $community = Community::factory()->create();
        $request = ArchitecturalRequest::factory()->for($community)->create();

        actingAs(teamMember(CompanyRole::Staff, $community->company, [$community]));
        get(route('communities.architectural-requests.show', [$community, $request]))->assertOk();
        get(route('communities.architectural-requests.letter', [$community, $request]))->assertNotFound();
        Livewire::test(RequestShow::class, ['community' => $community, 'architecturalRequest' => $request])->call('decide')->assertForbidden();
    });

    it('returns 404 for another company\'s request', function () {
        $foreign = ArchitecturalRequest::factory()->create();
        actingAs(companyAdmin());

        get(route('communities.architectural-requests.show', [$foreign->community_id, $foreign->id]))->assertNotFound();
    });
});

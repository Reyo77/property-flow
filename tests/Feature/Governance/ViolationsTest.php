<?php

use App\Actions\ArchitecturalRequests\DecideArchitecturalRequest;
use App\Actions\ArchitecturalRequests\SubmitArchitecturalRequest;
use App\Actions\Violations\CloseViolation;
use App\Actions\Violations\EscalateViolation;
use App\Actions\Violations\ReportViolation;
use App\Enums\ArchitecturalRequestStatus;
use App\Enums\CompanyRole;
use App\Enums\NotificationCategory;
use App\Enums\ResidencyType;
use App\Enums\SystemAccount;
use App\Enums\ViolationStage;
use App\Enums\ViolationStatus;
use App\Models\ArchitecturalRequest;
use App\Models\Community;
use App\Models\Invoice;
use App\Models\NotificationPreference;
use App\Models\Residency;
use App\Models\Resident;
use App\Models\Unit;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationNotice;
use App\Models\ViolationRule;
use App\Notifications\ArchitecturalRequestDecided;
use App\Notifications\ViolationNoticeIssued;
use App\Support\Finance\AccountBalances;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\artisan;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    travelTo(CarbonImmutable::parse('2026-10-01 12:00', 'America/Toronto'));
    Storage::fake('local');
});

/**
 * @return array{0: Community, 1: Unit, 2: User, 3: ViolationRule}
 */
function violationSetup(array $rule = []): array
{
    $community = Community::factory()->create(['timezone' => 'America/Toronto']);
    $unit = Unit::factory()->for($community)->create();
    $owner = Resident::factory()->for($community->company)->withLogin()->create();
    Residency::factory()->for($unit)->for($owner)->create(['type' => ResidencyType::Owner]);
    $rule = ViolationRule::factory()->for($community)->create(['cure_days' => 14, 'fine_cents' => 10000, 'max_fines' => 2, ...$rule]);

    return [$community, $unit, $owner->user, $rule];
}

function reportViolation(ViolationRule $rule, Unit $unit, array $photos = []): Violation
{
    return app(ReportViolation::class)->handle($rule, $unit, CarbonImmutable::now(), 'Bicycle and boxes stored on balcony', 'Balcony', $photos, companyAdmin($unit->community->company));
}

function escalateOn(Violation $violation, string $date): ?ViolationNotice
{
    travelTo(CarbonImmutable::parse("{$date} 06:00", 'America/Toronto'));

    return app(EscalateViolation::class)->handle($violation, CarbonImmutable::now('America/Toronto')->startOfDay());
}

describe('reporting', function () {
    it('logs a violation with photos and sends the owners a courtesy notice at once', function () {
        Notification::fake();
        [, $unit, $owner, $rule] = violationSetup();

        $violation = reportViolation($rule, $unit, [UploadedFile::fake()->image('balcony.jpg')]);

        expect($violation)->status->toBe(ViolationStatus::Open)->stage->toBe(ViolationStage::Courtesy)
            ->and($violation->next_action_on?->toDateString())->toBe('2026-10-16')
            ->and($violation->notices()->sole())->stage->toBe(ViolationStage::Courtesy)
            ->and($violation->notices()->sole()->cure_by?->toDateString())->toBe('2026-10-15')
            ->and($violation->attachments()->count())->toBe(1);

        Storage::disk('local')->assertExists($violation->attachments()->sole()->disk_path);
        Notification::assertSentTo($owner, ViolationNoticeIssued::class);
    });

    it('does not notify tenants, or owners who opted out', function () {
        Notification::fake();
        [$community, $unit, $owner, $rule] = violationSetup();
        $tenant = Resident::factory()->for($community->company)->withLogin()->create();
        Residency::factory()->for($unit)->for($tenant)->create(['type' => ResidencyType::Tenant, 'is_primary' => false]);
        NotificationPreference::factory()->for($owner)->create(['category' => NotificationCategory::Violations, 'in_app' => false]);

        reportViolation($rule, $unit);

        Notification::assertNothingSent();
    });

    it('refuses an inactive rule or a unit from another community', function (string $case) {
        [$community, $unit, , $rule] = violationSetup();

        match ($case) {
            'inactive rule' => reportViolation(tap($rule)->update(['is_active' => false]), $unit),
            'other community' => reportViolation($rule, Unit::factory()->for(Community::factory()->for($community->company))->create()),
        };
    })->with(['inactive rule', 'other community'])->throws(ValidationException::class);
});

describe('escalation', function () {
    it('climbs from courtesy notice to warning to fines, one step per cure period, and stops at the limit', function () {
        [$community, $unit, , $rule] = violationSetup();
        $violation = reportViolation($rule, $unit);

        expect(escalateOn($violation, '2026-10-15'))->toBeNull();                   // still inside the cure period
        expect(escalateOn($violation, '2026-10-16')?->stage)->toBe(ViolationStage::Warning);
        expect(escalateOn($violation, '2026-10-16'))->toBeNull();                   // same day again: nothing
        expect(escalateOn($violation, '2026-10-30'))->toBeNull();                   // warning's cure period ends Oct 30
        expect(escalateOn($violation, '2026-10-31')?->stage)->toBe(ViolationStage::Fine);
        expect(escalateOn($violation, '2026-11-14'))->toBeNull();
        expect(escalateOn($violation, '2026-11-15')?->stage)->toBe(ViolationStage::Fine);
        expect(escalateOn($violation, '2027-03-01'))->toBeNull();                   // max 2 fines: now the board's call

        $violation->refresh();
        expect($violation)->fines_issued->toBe(2)->next_action_on->toBeNull()->status->toBe(ViolationStatus::Open)
            ->and($violation->notices()->pluck('stage')->map->value->all())->toBe(['courtesy', 'warning', 'fine', 'fine'])
            ->and(Invoice::withoutGlobalScopes()->where('unit_id', $unit->id)->pluck('total_cents')->all())->toBe([10000, 10000])
            ->and(app(AccountBalances::class)->of($community, SystemAccount::Fines)->cents)->toBe(20000);
    });

    it('never fines twice for the same step, even if run concurrently', function () {
        [, $unit, , $rule] = violationSetup();
        $violation = reportViolation($rule, $unit);
        escalateOn($violation, '2026-10-16');

        $first = escalateOn($violation, '2026-11-01');
        $stale = Violation::withoutGlobalScopes()->findOrFail($violation->id);
        $second = app(EscalateViolation::class)->handle($stale, CarbonImmutable::parse('2026-11-01'));

        expect($first?->stage)->toBe(ViolationStage::Fine)->and($second)->toBeNull()
            ->and(Invoice::withoutGlobalScopes()->where('unit_id', $unit->id)->count())->toBe(1);
    });

    it('stops at the warning for a rule with no fine', function () {
        [, $unit, , $rule] = violationSetup(['fine_cents' => null]);
        $violation = reportViolation($rule, $unit);

        escalateOn($violation, '2026-10-16');

        expect($violation->fresh())->stage->toBe(ViolationStage::Warning)->next_action_on->toBeNull()
            ->and(escalateOn($violation, '2026-12-01'))->toBeNull();
    });

    it('lets a manager escalate early', function () {
        [, $unit, , $rule] = violationSetup();
        $violation = reportViolation($rule, $unit);

        $notice = app(EscalateViolation::class)->handle($violation, CarbonImmutable::parse('2026-10-02'), companyAdmin($unit->community->company), force: true);

        expect($notice?->stage)->toBe(ViolationStage::Warning);
    });

    it('stops escalating once resolved, keeping fines already issued', function () {
        [, $unit, , $rule] = violationSetup();
        $violation = reportViolation($rule, $unit);
        escalateOn($violation, '2026-10-16');
        escalateOn($violation, '2026-11-01');

        app(CloseViolation::class)->handle($violation->fresh() ?? $violation, ViolationStatus::Resolved, 'Balcony cleared', companyAdmin($unit->community->company));

        expect(escalateOn($violation, '2026-11-16'))->toBeNull()
            ->and($violation->fresh())->status->toBe(ViolationStatus::Resolved)->next_action_on->toBeNull()
            ->and(Invoice::withoutGlobalScopes()->where('unit_id', $unit->id)->count())->toBe(1)
            ->and(fn () => app(CloseViolation::class)->handle($violation->fresh() ?? $violation, ViolationStatus::Dismissed, null, companyAdmin($unit->community->company)))
            ->toThrow(LogicException::class, 'already closed');
    });

    it('is run daily by the scheduler', function () {
        [, $unit, , $rule] = violationSetup();
        $violation = reportViolation($rule, $unit);
        travelTo(CarbonImmutable::parse('2026-10-16 06:00', 'America/Toronto'));

        artisan('violations:escalate')->expectsOutputToContain('Escalated 1 violation(s).')->assertSuccessful();
        artisan('violations:escalate')->expectsOutputToContain('Escalated 0 violation(s).')->assertSuccessful();

        expect($violation->fresh()?->stage)->toBe(ViolationStage::Warning);
    });
});

describe('architectural requests', function () {
    it('lets an owner submit a request with plans, and the board approve it with conditions', function () {
        Notification::fake();
        [$community, $unit, $owner] = violationSetup();
        $board = teamMember(CompanyRole::BoardMember, $community->company, [$community]);

        $request = app(SubmitArchitecturalRequest::class)->handle($unit, $owner, 'Hardwood flooring', 'Replace carpet in living room', 'Oak Floors Inc.', CarbonImmutable::parse('2026-11-01'), [UploadedFile::fake()->create('plan.pdf', 200, 'application/pdf')]);
        app(DecideArchitecturalRequest::class)->startReview($request, $board);
        app(DecideArchitecturalRequest::class)->decide($request->fresh() ?? $request, ArchitecturalRequestStatus::ApprovedWithConditions, 'Acoustic underlay rated IIC 55 or better.', null, $board);

        expect($request->fresh())->status->toBe(ArchitecturalRequestStatus::ApprovedWithConditions)
            ->conditions->toBe('Acoustic underlay rated IIC 55 or better.')->decided_by_id->toBe($board->id)
            ->and($request->attachments()->count())->toBe(1);
        Notification::assertSentTo($owner, ArchitecturalRequestDecided::class);
    });

    it('requires conditions to approve conditionally and a reason to deny', function (ArchitecturalRequestStatus $decision, string $field) {
        [$community, $unit, $owner] = violationSetup();
        $request = app(SubmitArchitecturalRequest::class)->handle($unit, $owner, 'Fence', 'New fence', null, null, []);

        try {
            app(DecideArchitecturalRequest::class)->decide($request, $decision, '  ', null, companyAdmin($community->company));
            $this->fail('Expected a validation error');
        } catch (ValidationException $exception) {
            expect($exception->errors())->toHaveKey($field);
        }
    })->with([
        [ArchitecturalRequestStatus::ApprovedWithConditions, 'conditions'],
        [ArchitecturalRequestStatus::Denied, 'decision_notes'],
    ]);

    it('only accepts requests from owners', function () {
        [$community, $unit] = violationSetup();
        $tenant = Resident::factory()->for($community->company)->withLogin()->create();
        Residency::factory()->for($unit)->for($tenant)->create(['type' => ResidencyType::Tenant]);

        app(SubmitArchitecturalRequest::class)->handle($unit, $tenant->user, 'Paint', 'Repaint', null, null, []);
    })->throws(AuthorizationException::class);

    it('is decided once, and can be withdrawn only while open by its submitter', function () {
        [$community, $unit, $owner] = violationSetup();
        $admin = companyAdmin($community->company);
        $decide = app(DecideArchitecturalRequest::class);
        $request = app(SubmitArchitecturalRequest::class)->handle($unit, $owner, 'Window', 'New window', null, null, []);

        expect(fn () => $decide->withdraw($request, $admin))->toThrow(LogicException::class);

        $decide->decide($request, ArchitecturalRequestStatus::Approved, null, null, $admin);

        expect(fn () => $decide->decide($request->fresh() ?? $request, ArchitecturalRequestStatus::Denied, null, 'Changed our minds', $admin))->toThrow(LogicException::class)
            ->and(fn () => $decide->withdraw($request->fresh() ?? $request, $owner))->toThrow(LogicException::class);

        $second = app(SubmitArchitecturalRequest::class)->handle($unit, $owner, 'Door', 'New door', null, null, []);
        $decide->withdraw($second, $owner);

        expect($second->fresh()?->status)->toBe(ArchitecturalRequestStatus::Withdrawn)
            ->and(ArchitecturalRequest::count())->toBe(2);
    });
});

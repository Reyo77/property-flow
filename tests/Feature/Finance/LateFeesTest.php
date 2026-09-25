<?php

use App\Actions\Finance\AssessLateFees;
use App\Actions\Finance\IssueInvoice;
use App\Actions\Finance\RecordPayment;
use App\Actions\Finance\SendOverdueReminders;
use App\Actions\Finance\VoidInvoice;
use App\Enums\NotificationCategory;
use App\Enums\PaymentMethod;
use App\Enums\SystemAccount;
use App\Models\Community;
use App\Models\Invoice;
use App\Models\LateFeeRule;
use App\Models\NotificationPreference;
use App\Models\Unit;
use App\Notifications\InvoiceOverdue;
use App\Support\Finance\AccountBalances;
use App\Support\Finance\ChartOfAccounts;
use App\Support\Finance\InvoiceLineData;
use App\Support\Finance\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\artisan;
use function Pest\Laravel\travelTo;

/**
 * A community with a late fee rule "created" on 2026-08-01, and a unit billed $400 due 2026-09-01.
 *
 * @return array{0: Community, 1: Unit, 2: Invoice}
 */
function overdueSetup(array $rule = []): array
{
    travelTo(CarbonImmutable::parse('2026-08-01 12:00', 'America/Toronto'));
    $community = Community::factory()->create(['timezone' => 'America/Toronto']);
    LateFeeRule::factory()->for($community)->create(['grace_days' => 10, 'flat_cents' => 2500, ...$rule]);
    $unit = Unit::factory()->for($community)->create();
    $invoice = billDue($unit, 40000, '2026-09-01');

    return [$community, $unit, $invoice];
}

function billDue(Unit $unit, int $cents, string $dueOn): Invoice
{
    $due = CarbonImmutable::parse($dueOn);

    return app(IssueInvoice::class)->handle(
        $unit,
        $due->subDays(10)->min(CarbonImmutable::now()),
        $due,
        [new InvoiceLineData('Monthly fees', Money::of($cents), app(ChartOfAccounts::class)->account($unit->community, SystemAccount::Assessments))],
    );
}

function assessOn(Community $community, string $date): int
{
    travelTo(CarbonImmutable::parse("{$date} 03:00", $community->timezone));

    return app(AssessLateFees::class)->handle($community, CarbonImmutable::now($community->timezone));
}

function lateFees(Invoice $invoice): Collection
{
    return Invoice::withoutGlobalScopes()->where('billing_key', AssessLateFees::billingKey($invoice))->get();
}

describe('late fees', function () {
    it('waits out the grace period, then charges exactly once', function () {
        [$community, , $invoice] = overdueSetup();

        expect(assessOn($community, '2026-09-11'))->toBe(0)
            ->and(assessOn($community, '2026-09-12'))->toBe(1)
            ->and(assessOn($community, '2026-09-13'))->toBe(0)
            ->and(assessOn($community, '2026-12-01'))->toBe(0)
            ->and(lateFees($invoice))->toHaveCount(1);
    });

    it('bills the fee to the unit as late fee income, linked to the overdue invoice', function () {
        [$community, $unit, $invoice] = overdueSetup();

        assessOn($community, '2026-09-12');

        $fee = lateFees($invoice)->sole();
        expect($fee)->unit_id->toBe($unit->id)->total_cents->toBe(2500)
            ->and($fee->source->is($invoice))->toBeTrue()
            ->and($fee->issued_on->toDateString())->toBe('2026-09-12')
            ->and(app(AccountBalances::class)->of($community, SystemAccount::LateFees)->cents)->toBe(2500);
    });

    it('charges a percentage of what is still owing, rounded half away from zero', function (int $paidCents, int $expectedFee) {
        [$community, $unit, $invoice] = overdueSetup(['flat_cents' => null, 'percent_basis_points' => 150]);
        if ($paidCents > 0) {
            app(RecordPayment::class)->handle($unit, PaymentMethod::Cash, Money::of($paidCents), CarbonImmutable::parse('2026-09-05'));
        }

        assessOn($community, '2026-09-12');

        expect(lateFees($invoice)->sole()->total_cents)->toBe($expectedFee);
    })->with([
        'nothing paid: 1.5% of $400' => [0, 600],
        'partly paid: 1.5% of $123.33 = 184.995c' => [40000 - 12333, 185],
        'partly paid: 1.5% of $0.34 = 0.51c' => [40000 - 34, 1],
    ]);

    it('skips balances below the minimum, and fully paid or voided invoices', function () {
        [$community, $unit, $invoice] = overdueSetup(['minimum_balance_cents' => 5000]);
        app(RecordPayment::class)->handle($unit, PaymentMethod::Cash, Money::of(36000), CarbonImmutable::parse('2026-09-05'));
        $paid = billDue($unit, 1000, '2026-09-01');
        app(RecordPayment::class)->handle($unit, PaymentMethod::Cash, Money::of(1000), CarbonImmutable::parse('2026-09-05'));
        $voided = billDue(Unit::factory()->for($community)->create(), 90000, '2026-09-01');
        app(VoidInvoice::class)->handle($voided, CarbonImmutable::parse('2026-09-02'), 'Error');

        expect(assessOn($community, '2026-09-20'))->toBe(0)
            ->and(lateFees($invoice))->toBeEmpty()
            ->and(lateFees($paid))->toBeEmpty()
            ->and(lateFees($voided))->toBeEmpty();
    });

    it('never charges a late fee on a late fee', function () {
        [$community, , $invoice] = overdueSetup();

        assessOn($community, '2026-09-12');
        $fee = lateFees($invoice)->sole();

        expect(assessOn($community, '2026-10-30'))->toBe(0)
            ->and(lateFees($fee))->toBeEmpty();
    });

    it('does not fine invoices that fell due before the rule existed', function () {
        travelTo(CarbonImmutable::parse('2026-06-01 12:00', 'America/Toronto'));
        $community = Community::factory()->create(['timezone' => 'America/Toronto']);
        $unit = Unit::factory()->for($community)->create();
        $old = billDue($unit, 40000, '2026-07-01');
        travelTo(CarbonImmutable::parse('2026-08-01 12:00', 'America/Toronto'));
        LateFeeRule::factory()->for($community)->create();
        $new = billDue($unit, 40000, '2026-09-01');

        assessOn($community, '2026-09-20');

        expect(lateFees($old))->toBeEmpty()->and(lateFees($new))->toHaveCount(1);
    });

    it('does nothing without an active rule', function () {
        [$community, , $invoice] = overdueSetup(['is_active' => false]);

        expect(assessOn($community, '2026-10-01'))->toBe(0)->and(lateFees($invoice))->toBeEmpty();
    });

    it('is run daily by the command for every community with a rule', function () {
        [$community, , $invoice] = overdueSetup();
        travelTo(CarbonImmutable::parse('2026-09-15 03:00', $community->timezone));

        artisan('finance:assess-late-fees')->expectsOutputToContain('Charged 1 late fee(s).')->assertSuccessful();
        artisan('finance:assess-late-fees')->expectsOutputToContain('Charged 0 late fee(s).')->assertSuccessful();

        expect(lateFees($invoice))->toHaveCount(1);
    });
});

describe('overdue reminders', function () {
    it('reminds the unit\'s current residents, then again only after a week', function () {
        Notification::fake();
        [$community, $unit] = overdueSetup();
        $current = residentOf($community, ['unit_id' => $unit->id]);
        $former = residentOf($community, ['unit_id' => $unit->id, 'moved_out_on' => '2026-07-01']);
        $neighbour = residentOf($community);
        $remind = fn (string $at) => app(SendOverdueReminders::class)->handle($community, CarbonImmutable::parse($at, $community->timezone));

        expect($remind('2026-09-01 09:00'))->toBe(0)
            ->and($remind('2026-09-02 09:00'))->toBe(1)
            ->and($remind('2026-09-08 09:00'))->toBe(0)
            ->and($remind('2026-09-09 09:00'))->toBe(1);

        Notification::assertSentToTimes($current->user, InvoiceOverdue::class, 2);
        Notification::assertNotSentTo([$former->user, $neighbour->user], InvoiceOverdue::class);
    });

    it('stops once the invoice is paid', function () {
        Notification::fake();
        [$community, $unit] = overdueSetup();
        residentOf($community, ['unit_id' => $unit->id]);
        app(RecordPayment::class)->handle($unit, PaymentMethod::Cash, Money::of(40000), CarbonImmutable::parse('2026-09-01'));

        expect(app(SendOverdueReminders::class)->handle($community, CarbonImmutable::parse('2026-09-05 09:00')))->toBe(0);
        Notification::assertNothingSent();
    });

    it('respects a resident who turned billing notifications off', function () {
        Notification::fake();
        [$community, $unit] = overdueSetup();
        $resident = residentOf($community, ['unit_id' => $unit->id]);
        NotificationPreference::factory()->for($resident->user)->create(['category' => NotificationCategory::Billing, 'in_app' => false]);

        app(SendOverdueReminders::class)->handle($community, CarbonImmutable::parse('2026-09-05 09:00'));

        Notification::assertNothingSent();
    });

    it('stores a readable in-app notification', function () {
        [$community, $unit, $invoice] = overdueSetup();
        $resident = residentOf($community, ['unit_id' => $unit->id]);

        artisan('finance:send-overdue-reminders')->assertSuccessful();
        travelTo(CarbonImmutable::parse('2026-09-05 09:00', $community->timezone));
        artisan('finance:send-overdue-reminders')->expectsOutputToContain('Sent reminders for 1 invoice(s).')->assertSuccessful();

        $notification = $resident->user->notifications()->sole();
        expect($notification->data['title'])->toBe('Payment overdue')
            ->and($notification->data['excerpt'])->toBe("{$invoice->displayNumber()} for \$400.00 was due Sep 1, 2026.");
    });
});

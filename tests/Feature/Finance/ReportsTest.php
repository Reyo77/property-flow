<?php

use App\Actions\Finance\DecideVendorBill;
use App\Actions\Finance\IssueInvoice;
use App\Actions\Finance\PayVendorBill;
use App\Actions\Finance\RecordPayment;
use App\Actions\Finance\ReversePayment;
use App\Actions\Finance\SubmitVendorBill;
use App\Actions\Finance\VoidInvoice;
use App\Enums\PaymentMethod;
use App\Enums\PaymentReversalReason;
use App\Enums\SystemAccount;
use App\Models\Account;
use App\Models\BudgetLine;
use App\Models\Community;
use App\Models\Unit;
use App\Models\Vendor;
use App\Support\Finance\AccountBalances;
use App\Support\Finance\ChartOfAccounts;
use App\Support\Finance\FinancialReports;
use App\Support\Finance\FiscalYears;
use App\Support\Finance\InvoiceLineData;
use App\Support\Finance\Money;
use Carbon\CarbonImmutable;

use function Pest\Laravel\travelTo;

/**
 * A small community with a known quarter of activity (all amounts in dollars):
 *
 * Jan: A billed 300 (due Jan 1), B billed 500 (due Jan 1), C billed 200 (voided)
 *      A pays 300; B pays 200; a 1,000 repairs bill is approved and paid
 * Feb: A billed 300 (due Feb 1), B billed 500 (due Feb 1), late fee on B 25 (due Feb 15)
 *      A pays 400 (100 left as credit); a 150 cheque from B bounces
 * Mar: A billed 300 (due Mar 1) – taken by A's 100 credit, 200 left; B billed 500 (due Mar 1)
 *      a 600 utilities bill is approved, not paid
 *
 * @return array{community: Community, a: Unit, b: Unit, c: Unit}
 */
function reportScenario(): array
{
    travelTo(CarbonImmutable::parse('2026-01-01 09:00', 'America/Toronto'));
    $community = Community::factory()->create(['timezone' => 'America/Toronto']);
    $admin = companyAdmin($community->company);
    $chart = app(ChartOfAccounts::class);
    $a = Unit::factory()->for($community)->create(['number' => 'A']);
    $b = Unit::factory()->for($community)->create(['number' => 'B']);
    $c = Unit::factory()->for($community)->create(['number' => 'C']);
    $fees = $chart->account($community, SystemAccount::Assessments);
    $lateFees = $chart->account($community, SystemAccount::LateFees);
    $bill = fn (Unit $unit, int $dollars, string $due, $account = null) => app(IssueInvoice::class)->handle(
        $unit, CarbonImmutable::parse($due), CarbonImmutable::parse($due), [new InvoiceLineData('Fees', Money::of($dollars * 100), $account ?? $fees)],
    );
    $pay = fn (Unit $unit, int $dollars, string $on) => app(RecordPayment::class)->handle($unit, PaymentMethod::Cheque, Money::of($dollars * 100), CarbonImmutable::parse($on));
    $vendorBill = function (string $code, int $dollars, string $on) use ($community, $admin) {
        $bill = app(SubmitVendorBill::class)->handle(
            $community, Vendor::factory()->create(['company_id' => $community->company_id]), Account::withoutGlobalScopes()->where('community_id', $community->id)->where('code', $code)->sole(),
            Money::of($dollars * 100), CarbonImmutable::parse($on), CarbonImmutable::parse($on)->addDays(30), 'Work', null, $admin,
        );
        app(DecideVendorBill::class)->approve($bill, $admin);

        return $bill;
    };

    $bill($a, 300, '2026-01-01');
    $bill($b, 500, '2026-01-01');
    app(VoidInvoice::class)->handle($bill($c, 200, '2026-01-01'), CarbonImmutable::parse('2026-01-03'), 'Wrong unit');
    $pay($a, 300, '2026-01-05');
    $pay($b, 200, '2026-01-10');
    $repairs = $vendorBill('5000', 1000, '2026-01-20');
    app(PayVendorBill::class)->handle($repairs, PaymentMethod::Cheque, CarbonImmutable::parse('2026-01-25'), null, $admin);

    $bill($a, 300, '2026-02-01');
    $bill($b, 500, '2026-02-01');
    $bill($b, 25, '2026-02-15', $lateFees);
    $pay($a, 400, '2026-02-03');
    app(ReversePayment::class)->handle($pay($b, 150, '2026-02-05'), PaymentReversalReason::Nsf, CarbonImmutable::parse('2026-02-08'));

    $bill($a, 300, '2026-03-01');
    $bill($b, 500, '2026-03-01');
    $vendorBill('5100', 600, '2026-03-10');

    travelTo(CarbonImmutable::parse('2026-03-31 17:00', 'America/Toronto'));

    return ['community' => $community, 'a' => $a, 'b' => $b, 'c' => $c];
}

function reports(): FinancialReports
{
    return app(FinancialReports::class);
}

function dollars(?int $cents): ?float
{
    return $cents === null ? null : $cents / 100;
}

describe('income statement', function () {
    it('shows the quarter\'s income, expenses and net income', function () {
        ['community' => $community] = reportScenario();

        $report = reports()->incomeStatement($community, CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-03-31'));

        expect(dollars($report->centsFor('4000 · Common expense fees')[0]))->toBe(2400.0)
            ->and(dollars($report->centsFor('4100 · Late fees')[0]))->toBe(25.0)
            ->and(dollars($report->centsFor('Total income')[0]))->toBe(2425.0)
            ->and(dollars($report->centsFor('5000 · Repairs & maintenance')[0]))->toBe(1000.0)
            ->and(dollars($report->centsFor('5100 · Utilities')[0]))->toBe(600.0)
            ->and(dollars($report->centsFor('Total expenses')[0]))->toBe(1600.0)
            ->and(dollars($report->centsFor('Net income')[0]))->toBe(825.0);
    });

    it('covers only the period asked for', function () {
        ['community' => $community] = reportScenario();

        $february = reports()->incomeStatement($community, CarbonImmutable::parse('2026-02-01'), CarbonImmutable::parse('2026-02-28'));

        expect(dollars($february->centsFor('Total income')[0]))->toBe(825.0)
            ->and(dollars($february->centsFor('Total expenses')[0]))->toBe(0.0)
            ->and($february->centsFor('5000 · Repairs & maintenance'))->toBeNull();
    });
});

describe('balance sheet', function () {
    it('balances, with the quarter\'s surplus in equity', function () {
        ['community' => $community] = reportScenario();

        $report = reports()->balanceSheet($community, CarbonImmutable::parse('2026-03-31'));

        // Cash: 300 + 200 + 400 + 150 − 150 − 1,000 = −100
        expect(dollars($report->centsFor('1000 · Operating bank account')[0]))->toBe(-100.0)
            // Receivables: A 200 + B (1,525 − 200) = 1,525
            ->and(dollars($report->centsFor('1100 · Accounts receivable')[0]))->toBe(1525.0)
            ->and(dollars($report->centsFor('Total assets')[0]))->toBe(1425.0)
            ->and(dollars($report->centsFor('2000 · Accounts payable')[0]))->toBe(600.0)
            ->and(dollars($report->centsFor('Accumulated surplus (deficit)')[0]))->toBe(825.0)
            ->and(dollars($report->centsFor('Total liabilities and equity')[0]))->toBe(1425.0)
            ->and($report->centsFor('Difference')[0])->toBe(0);
    });

    it('balances at every date, not just the end', function (string $date) {
        ['community' => $community] = reportScenario();

        expect(reports()->balanceSheet($community, CarbonImmutable::parse($date))->centsFor('Difference')[0])->toBe(0);
    })->with(['2025-12-31', '2026-01-04', '2026-01-25', '2026-02-06', '2026-02-08', '2026-03-15']);

    it('agrees with the income statement: surplus to date equals net income to date', function () {
        ['community' => $community] = reportScenario();

        $surplus = reports()->balanceSheet($community, CarbonImmutable::parse('2026-02-28'))->centsFor('Accumulated surplus (deficit)')[0];
        $net = reports()->incomeStatement($community, CarbonImmutable::parse('2000-01-01'), CarbonImmutable::parse('2026-02-28'))->centsFor('Net income')[0];

        expect($surplus)->toBe($net);
    });
});

describe('aged receivables', function () {
    it('buckets each unit\'s open balances by days overdue and nets credits', function () {
        ['community' => $community] = reportScenario();

        $report = reports()->agedReceivables($community, CarbonImmutable::parse('2026-03-31'));

        // columns: current, 1–30, 31–60, 61–90, over 90, credits, total
        expect(array_map('dollars', $report->centsFor('A')))->toBe([null, 200.0, null, null, null, null, 200.0])
            ->and(array_map('dollars', $report->centsFor('B')))->toBe([null, 500.0, 525.0, 300.0, null, null, 1325.0])
            ->and($report->centsFor('C'))->toBeNull()
            ->and(dollars($report->centsFor('Total')[6]))->toBe(1525.0);
    });

    it('always totals to the receivables account balance', function (string $asOf) {
        ['community' => $community] = reportScenario();

        $total = reports()->agedReceivables($community, CarbonImmutable::parse($asOf))->centsFor('Total')[6];

        expect($total)->toBe(app(AccountBalances::class)->of($community, SystemAccount::Receivables)->cents);
    })->with(['2026-03-31', '2026-06-30']);

    it('shows a credit on account as a negative amount', function () {
        travelTo(CarbonImmutable::parse('2026-03-01 09:00', 'America/Toronto'));
        $community = Community::factory()->create();
        $unit = Unit::factory()->for($community)->create(['number' => 'D']);
        app(RecordPayment::class)->handle($unit, PaymentMethod::Cash, Money::of(5000), CarbonImmutable::parse('2026-03-01'));

        expect(array_map('dollars', reports()->agedReceivables($community, CarbonImmutable::parse('2026-03-31'))->centsFor('D')))
            ->toBe([null, null, null, null, null, -50.0, -50.0]);
    });

    it('moves balances into older buckets as time passes', function () {
        ['community' => $community] = reportScenario();

        $later = reports()->agedReceivables($community, CarbonImmutable::parse('2026-05-15'));

        // Mar 1 → 75 days (61–90), Feb 1/15 → 103/89 days, Jan 1 → 134 days
        expect(array_map('dollars', $later->centsFor('B')))->toBe([null, null, null, 525.0, 800.0, null, 1325.0]);
    });
});

describe('budget vs actual', function () {
    it('compares the year-to-date budget with actuals, favourable variances positive', function () {
        ['community' => $community] = reportScenario();
        $year = app(FiscalYears::class)->covering($community, CarbonImmutable::parse('2026-03-31'));
        $chart = app(ChartOfAccounts::class);
        $budget = fn (SystemAccount|string $account, int $dollars) => BudgetLine::factory()->for($community)->create([
            'fiscal_year_id' => $year->id,
            'account_id' => $account instanceof SystemAccount
                ? $chart->account($community, $account)->id
                : Account::withoutGlobalScopes()->where('community_id', $community->id)->where('code', $account)->value('id'),
            'annual_cents' => $dollars * 100,
        ]);
        $budget(SystemAccount::Assessments, 9_600);
        $budget('5000', 3_000);
        $budget('5200', 1_200);

        $report = reports()->budgetVsActual($community, $year, CarbonImmutable::parse('2026-03-31'));

        // columns: annual, to date (3 of 12 months), actual, variance
        expect(array_map('dollars', $report->centsFor('4000 · Common expense fees')))->toBe([9600.0, 2400.0, 2400.0, 0.0])
            ->and(array_map('dollars', $report->centsFor('4100 · Late fees')))->toBe([0.0, 0.0, 25.0, 25.0])
            ->and(array_map('dollars', $report->centsFor('5000 · Repairs & maintenance')))->toBe([3000.0, 750.0, 1000.0, -250.0])
            ->and(array_map('dollars', $report->centsFor('5100 · Utilities')))->toBe([0.0, 0.0, 600.0, -600.0])
            ->and(array_map('dollars', $report->centsFor('5200 · Insurance')))->toBe([1200.0, 300.0, 0.0, 300.0])
            ->and(array_map('dollars', $report->centsFor('Net')))->toBe([5400.0, 1350.0, 825.0, -525.0]);
    });

    it('splits an awkward annual budget into months that add back to the whole year', function () {
        travelTo(CarbonImmutable::parse('2026-12-31 12:00', 'America/Toronto'));
        $community = Community::factory()->create();
        $year = app(FiscalYears::class)->covering($community, CarbonImmutable::parse('2026-06-01'));
        BudgetLine::factory()->for($community)->create([
            'fiscal_year_id' => $year->id,
            'account_id' => app(ChartOfAccounts::class)->account($community, SystemAccount::Assessments)->id,
            'annual_cents' => 100_001,
        ]);

        $toDate = fn (string $through) => reports()->budgetVsActual($community, $year, CarbonImmutable::parse($through))->centsFor('4000 · Common expense fees')[1];

        expect($toDate('2026-01-15'))->toBe(8334)
            ->and($toDate('2026-12-31'))->toBe(100_001)
            ->and($toDate('2027-06-01'))->toBe(100_001)
            ->and($toDate('2025-12-01'))->toBe(0);
    });
});

describe('general ledger', function () {
    it('lists each account\'s lines with running balances that close at the account balance', function () {
        ['community' => $community] = reportScenario();
        $cash = app(ChartOfAccounts::class)->account($community, SystemAccount::Cash);

        $report = reports()->generalLedger($community, CarbonImmutable::parse('2026-02-01'), CarbonImmutable::parse('2026-03-31'), $cash->id);

        expect(array_map('dollars', $report->centsFor('Opening balance')))->toBe([null, null, -500.0])
            ->and(array_map('dollars', $report->centsFor('Closing balance · 1000 · Operating bank account')))->toBe([550.0, 150.0, -100.0])
            ->and(collect($report->rows())->where('style', 'section')->pluck('label')->all())->toBe(['1000 · Operating bank account']);
    });

    it('closes every account at its balance sheet amount', function () {
        ['community' => $community] = reportScenario();

        $ledger = reports()->generalLedger($community, CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-03-31'));
        $balances = app(AccountBalances::class)->forCommunity($community);

        foreach (Account::withoutGlobalScopes()->where('community_id', $community->id)->get() as $account) {
            $closing = $ledger->centsFor("Closing balance · {$account->label()}");

            expect($closing === null ? 0 : $closing[2])->toBe($balances->get($account->id)?->cents ?? 0, $account->label());
        }
    });
});

it('exports exactly what it shows', function () {
    ['community' => $community] = reportScenario();

    $rows = reports()->incomeStatement($community, CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-03-31'))->toArray();

    expect($rows[0])->toBe(['', 'Amount'])
        ->and($rows)->toContain(['Income', ''])
        ->and($rows)->toContain(['4000 · Common expense fees', '2400.00'])
        ->and(end($rows))->toBe(['Net income', '825.00']);
});

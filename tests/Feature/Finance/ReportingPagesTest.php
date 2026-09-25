<?php

use App\Actions\Finance\IssueInvoice;
use App\Actions\Finance\RecordPayment;
use App\Enums\CompanyRole;
use App\Enums\FinancialReport;
use App\Enums\PaymentMethod;
use App\Enums\SystemAccount;
use App\Livewire\Finance\BankStatementShow;
use App\Livewire\Finance\Budgets;
use App\Livewire\Finance\Reconciliation;
use App\Livewire\Finance\Reports;
use App\Models\BankStatement;
use App\Models\BudgetLine;
use App\Models\Community;
use App\Models\Unit;
use App\Models\User;
use App\Support\Finance\ChartOfAccounts;
use App\Support\Finance\FiscalYears;
use App\Support\Finance\InvoiceLineData;
use App\Support\Finance\Money;
use App\Support\Finance\ReportExport;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\travelBack;
use function Pest\Laravel\travelTo;

beforeEach(fn () => travelTo(CarbonImmutable::parse('2026-10-05 12:00', 'America/Toronto')));

/**
 * A community with a $500 invoice and a $300 payment in September.
 */
function reportingCommunity(?User $user = null): Community
{
    $community = Community::factory()->for(($user ?? companyAdmin())->company)->create();
    $unit = Unit::factory()->for($community)->create(['number' => '101']);
    app(IssueInvoice::class)->handle(
        $unit, CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-09-01'),
        [new InvoiceLineData('Fees', Money::of(50000), app(ChartOfAccounts::class)->account($community, SystemAccount::Assessments))],
    );
    app(RecordPayment::class)->handle($unit, PaymentMethod::Cheque, Money::of(30000), CarbonImmutable::parse('2026-09-10'));

    return $community;
}

describe('reports page', function () {
    it('shows each report for the chosen dates', function (string $report, string $expected) {
        $admin = companyAdmin();
        $community = reportingCommunity($admin);
        actingAs($admin);

        Livewire::test(Reports::class, ['community' => $community])
            ->set('report', $report)
            ->set('from', '2026-09-01')
            ->set('to', '2026-09-30')
            ->assertSee($expected)
            ->assertDontSee('Choose a valid date range');
    })->with([
        'income statement' => ['income-statement', '$500.00'],
        'balance sheet' => ['balance-sheet', 'Accumulated surplus'],
        'aged receivables' => ['aged-receivables', '$200.00'],
        'budget vs actual' => ['budget-vs-actual', 'Budget to date'],
        'general ledger' => ['general-ledger', 'Opening balance'],
    ]);

    it('asks for a valid range instead of failing', function () {
        $admin = companyAdmin();
        $community = reportingCommunity($admin);
        actingAs($admin);

        Livewire::test(Reports::class, ['community' => $community])
            ->set('from', '2026-09-30')->set('to', '2026-09-01')
            ->assertSee('Choose a valid date range')
            ->set('to', 'not-a-date')
            ->assertSee('Choose a valid date range');
    });

    it('exports a report as CSV and Excel with the same figures', function (string $format) {
        Excel::fake();
        $admin = companyAdmin();
        $community = reportingCommunity($admin);
        actingAs($admin);

        get(route('communities.finance.reports.export', [$community, 'report' => 'income-statement', 'format' => $format, 'from' => '2026-09-01', 'to' => '2026-09-30']))->assertOk();

        $filename = Str::slug("{$community->name} income-statement 2026-09-30").".{$format}";
        Excel::assertDownloaded($filename, function (ReportExport $export) {
            $rows = $export->array();

            return in_array(['4000 · Common expense fees', '500.00'], $rows, true) && end($rows) === ['Net income', '500.00'];
        });
    })->with(['csv', 'xlsx']);

    it('validates export parameters', function (array $query) {
        $admin = companyAdmin();
        $community = reportingCommunity($admin);
        actingAs($admin);

        get(route('communities.finance.reports.export', [$community, ...$query]))->assertSessionHasErrors();
    })->with([
        'unknown report' => [['report' => 'profit', 'format' => 'csv', 'from' => '2026-09-01', 'to' => '2026-09-30']],
        'unknown format' => [['report' => 'balance-sheet', 'format' => 'pdf', 'from' => '2026-09-01', 'to' => '2026-09-30']],
        'backwards range' => [['report' => 'balance-sheet', 'format' => 'csv', 'from' => '2026-09-30', 'to' => '2026-09-01']],
        'another community\'s account' => [['report' => 'general-ledger', 'format' => 'csv', 'from' => '2026-09-01', 'to' => '2026-09-30', 'account' => 999999]],
    ]);

    it('keeps reports and exports from staff without finance access and from other companies', function () {
        $community = reportingCommunity();
        $query = ['report' => FinancialReport::BalanceSheet->value, 'format' => 'csv', 'from' => '2026-09-01', 'to' => '2026-09-30'];

        actingAs(teamMember(CompanyRole::Staff, $community->company, [$community]));
        get(route('communities.finance.reports.export', [$community, ...$query]))->assertForbidden();

        actingAs(companyAdmin());
        get(route('communities.finance.reports.export', [$community, ...$query]))->assertNotFound();
    });
});

describe('budget page', function () {
    it('saves an annual budget per account, and clears lines set to blank', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $assessments = app(ChartOfAccounts::class)->account($community, SystemAccount::Assessments);
        $repairs = $community->accounts()->where('code', '5000')->sole();
        actingAs($admin);

        Livewire::test(Budgets::class, ['community' => $community])
            ->set("amounts.{$assessments->id}", '324,000')
            ->set("amounts.{$repairs->id}", '60,000.50')
            ->assertSee('$27,000.00')
            ->assertSeeHtml('$263,999.50')
            ->call('save')
            ->assertHasNoErrors();

        $year = app(FiscalYears::class)->covering($community, CarbonImmutable::now());
        expect(BudgetLine::where('fiscal_year_id', $year->id)->pluck('annual_cents', 'account_id')->all())
            ->toEqual([$assessments->id => 32_400_000, $repairs->id => 6_000_050]);

        Livewire::test(Budgets::class, ['community' => $community])
            ->assertSet("amounts.{$assessments->id}", '324000.00')
            ->set("amounts.{$repairs->id}", '')
            ->call('save');

        expect(BudgetLine::count())->toBe(1);
    });

    it('keeps next year\'s budget separate', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $assessments = app(ChartOfAccounts::class)->account($community, SystemAccount::Assessments);
        actingAs($admin);

        Livewire::test(Budgets::class, ['community' => $community])
            ->set('year', 'next')
            ->set("amounts.{$assessments->id}", '1000')
            ->call('save')
            ->set('year', 'current')
            ->assertSet("amounts.{$assessments->id}", '');

        expect(BudgetLine::sole()->fiscalYear->starts_on->toDateString())->toBe('2027-01-01');
    });

    it('rejects amounts that are not money', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $assessments = app(ChartOfAccounts::class)->account($community, SystemAccount::Assessments);
        actingAs($admin);

        Livewire::test(Budgets::class, ['community' => $community])
            ->set("amounts.{$assessments->id}", '-5')
            ->call('save')
            ->assertHasErrors("amounts.{$assessments->id}");

        expect(BudgetLine::count())->toBe(0);
    });

    it('lets a board member read the budget but not change it', function () {
        $community = Community::factory()->create();
        actingAs(teamMember(CompanyRole::BoardMember, $community->company, [$community]));

        Livewire::test(Budgets::class, ['community' => $community])->assertOk()->call('save')->assertForbidden();
    });
});

describe('reconciliation pages', function () {
    it('imports a statement, resolves a bank charge and completes the reconciliation', function () {
        travelBack(); // Livewire treats temporary uploads from "the past" as expired.
        $admin = companyAdmin();
        $community = reportingCommunity($admin);
        $fees = $community->accounts()->where('code', '5900')->sole();
        actingAs($admin);

        Livewire::test(Reconciliation::class, ['community' => $community])
            ->call('create')
            ->set('file', UploadedFile::fake()->createWithContent('sept.csv', "date,description,amount\n2026-09-11,CHQ DEP,300.00\n2026-09-30,SERVICE CHARGE,-5.00\n"))
            ->set('starts_on', '2026-09-01')
            ->set('ends_on', '2026-09-30')
            ->set('closing_balance', '295.00')
            ->call('import')
            ->assertHasNoErrors()
            ->assertRedirect();

        $statement = BankStatement::sole();
        $charge = $statement->lines()->where('description', 'SERVICE CHARGE')->sole();

        Livewire::test(BankStatementShow::class, ['community' => $community, 'bankStatement' => $statement])
            ->assertSee('Unmatched')
            ->call('complete')
            ->call('selectLine', $charge->id)
            ->set('counter_account_id', (string) $fees->id)
            ->call('record')
            ->assertHasNoErrors()
            ->call('complete');

        expect($statement->fresh()?->isReconciled())->toBeTrue();
    });

    it('shows a readable error for a bad file', function () {
        travelBack();
        $admin = companyAdmin();
        $community = reportingCommunity($admin);
        actingAs($admin);

        Livewire::test(Reconciliation::class, ['community' => $community])
            ->call('create')
            ->set('file', UploadedFile::fake()->createWithContent('bad.csv', "date,description,amount\n2026-09-11,DEP,lots\n"))
            ->set('closing_balance', '0')
            ->call('import')
            ->assertHasErrors(['file' => 'Row 2 has an amount that is not a number.']);

        expect(BankStatement::count())->toBe(0);
    });

    it('lets a board member look at statements but not import or reconcile', function () {
        $community = reportingCommunity();
        $statement = BankStatement::factory()->for($community)->create();
        actingAs(teamMember(CompanyRole::BoardMember, $community->company, [$community]));

        get(route('communities.finance.reconciliation.show', [$community, $statement]))->assertOk();
        Livewire::test(Reconciliation::class, ['community' => $community])->call('create')->assertForbidden();
        Livewire::test(BankStatementShow::class, ['community' => $community, 'bankStatement' => $statement])->call('autoMatch')->assertForbidden();
    });

    it('returns 404 for another company\'s or another community\'s statement', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $sameCompany = BankStatement::factory()->for(Community::factory()->for($admin->company))->create();
        $otherCompany = BankStatement::factory()->create();
        actingAs($admin);

        get(route('communities.finance.reconciliation.show', [$community, $sameCompany]))->assertNotFound();
        get(route('communities.finance.reconciliation.show', [$otherCompany->community_id, $otherCompany->id]))->assertNotFound();
    });
});

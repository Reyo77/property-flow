<?php

namespace App\Support\Finance;

use App\Enums\AccountType;
use App\Enums\FinancialReport;
use App\Models\Account;
use App\Models\BudgetLine;
use App\Models\Community;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Unit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The standard reports, all read straight from the ledger (aged receivables also from invoices
 * and payments, which the ledger agrees with). Each returns a {@see ReportTable}.
 */
class FinancialReports
{
    public function __construct(
        private readonly AccountBalances $accountBalances,
        private readonly FiscalYears $fiscalYears,
    ) {}

    /**
     * Builds a report from already-validated parameters, the one entry point for both the page
     * and the export, so they can never disagree.
     */
    public function build(Community $community, FinancialReport $report, CarbonImmutable $from, CarbonImmutable $to, ?int $accountId = null): ReportTable
    {
        return match ($report) {
            FinancialReport::IncomeStatement => $this->incomeStatement($community, $from, $to),
            FinancialReport::GeneralLedger => $this->generalLedger($community, $from, $to, $accountId),
            FinancialReport::BalanceSheet => $this->balanceSheet($community, $to),
            FinancialReport::AgedReceivables => $this->agedReceivables($community, $to),
            FinancialReport::BudgetVsActual => $this->budgetVsActual($community, $this->fiscalYears->covering($community, $to), $to),
        };
    }

    public function incomeStatement(Community $community, CarbonImmutable $from, CarbonImmutable $to): ReportTable
    {
        $movements = $this->accountBalances->forCommunity($community, $to, $from);
        $accounts = $this->accounts($community);
        $currency = $community->currency;

        $table = new ReportTable(__('Income statement'), $this->period($from, $to), [__('Amount')]);

        $totalIncome = $this->sectionOfMovements($table, __('Income'), $accounts->where('type', AccountType::Income), $movements, $currency, __('Total income'));
        $totalExpenses = $this->sectionOfMovements($table, __('Expenses'), $accounts->where('type', AccountType::Expense), $movements, $currency, __('Total expenses'));

        return $table->total(__('Net income'), [$totalIncome->minus($totalExpenses)], grand: true);
    }

    /**
     * Assets = liabilities + equity, with income and expenses since the beginning folded into
     * equity as the accumulated surplus (years are closed virtually, never by posting).
     */
    public function balanceSheet(Community $community, CarbonImmutable $asOf): ReportTable
    {
        $balances = $this->accountBalances->forCommunity($community, $asOf);
        $accounts = $this->accounts($community);
        $currency = $community->currency;

        $table = new ReportTable(__('Balance sheet'), __('As of :date', ['date' => $asOf->toFormattedDateString()]), [__('Amount')]);

        $assets = $this->sectionOfMovements($table, __('Assets'), $accounts->where('type', AccountType::Asset), $balances, $currency, __('Total assets'));

        $liabilities = $this->sectionOfMovements($table, __('Liabilities'), $accounts->where('type', AccountType::Liability), $balances, $currency, __('Total liabilities'));

        $table->section(__('Equity'));
        $equity = Money::zero($currency);

        foreach ($accounts->where('type', AccountType::Equity) as $account) {
            $balance = $balances->get($account->id) ?? Money::zero($currency);
            $equity = $equity->plus($balance);

            if (! $balance->isZero()) {
                $table->line($account->label(), [$balance]);
            }
        }

        $surplus = $this->sum($accounts->where('type', AccountType::Income), $balances, $currency)
            ->minus($this->sum($accounts->where('type', AccountType::Expense), $balances, $currency));
        $table->line(__('Accumulated surplus (deficit)'), [$surplus]);
        $equity = $equity->plus($surplus);
        $table->total(__('Total equity'), [$equity]);

        return $table->total(__('Total liabilities and equity'), [$liabilities->plus($equity)], grand: true)
            ->total(__('Difference'), [$assets->minus($liabilities->plus($equity))]);
    }

    /**
     * What each unit owes, by how long it has been overdue, less any credit on account. The grand
     * total equals the receivables account's balance.
     */
    public function agedReceivables(Community $community, CarbonImmutable $asOf): ReportTable
    {
        $currency = $community->currency;
        $buckets = [__('Current'), __('1–30 days'), __('31–60 days'), __('61–90 days'), __('Over 90 days'), __('Credits'), __('Total')];
        $table = new ReportTable(__('Aged receivables'), __('As of :date', ['date' => $asOf->toFormattedDateString()]), $buckets, __('Unit'));

        $byUnit = [];

        $invoices = Invoice::query()->withoutGlobalScopes()->where('community_id', $community->id)->whereNull('voided_at')->withPaid()->get();

        foreach ($invoices as $invoice) {
            $balance = $invoice->balanceCents();

            if ($balance <= 0) {
                continue;
            }

            $daysOverdue = (int) $invoice->due_on->startOfDay()->diffInDays($asOf->startOfDay(), false);
            $bucket = match (true) {
                $daysOverdue <= 0 => 0,
                $daysOverdue <= 30 => 1,
                $daysOverdue <= 60 => 2,
                $daysOverdue <= 90 => 3,
                default => 4,
            };

            $byUnit[$invoice->unit_id][$bucket] = ($byUnit[$invoice->unit_id][$bucket] ?? 0) + $balance;
        }

        $payments = Payment::query()->withoutGlobalScopes()->where('community_id', $community->id)->whereNull('reversed_at')
            ->withSum('allocations as allocated_cents', 'amount_cents')->get();

        foreach ($payments as $payment) {
            $unapplied = $payment->amount_cents - (int) $payment->getAttribute('allocated_cents');

            if ($unapplied > 0) {
                $byUnit[$payment->unit_id][5] = ($byUnit[$payment->unit_id][5] ?? 0) - $unapplied;
            }
        }

        $units = Unit::query()->withoutGlobalScopes()->withTrashed()->whereIn('id', array_keys($byUnit))->with('building')->get()->keyBy('id');
        $totals = array_fill(0, 7, 0);

        foreach ($units->sortBy(fn (Unit $unit) => $unit->label(), SORT_NATURAL) as $unit) {
            $amounts = $byUnit[$unit->id];
            $row = [];

            foreach (range(0, 5) as $bucket) {
                $row[] = $amounts[$bucket] ?? 0;
                $totals[$bucket] += $amounts[$bucket] ?? 0;
            }

            $row[] = array_sum($row);
            $totals[6] += $row[6];

            $table->line($unit->label(), array_map(fn (int $cents) => $cents === 0 ? null : Money::of($cents, $currency), $row), ['unit_id' => $unit->id]);
        }

        return $table->total(__('Total'), array_map(fn (int $cents) => Money::of($cents, $currency), $totals), grand: true);
    }

    /**
     * Each income and expense account's budget against actual for the fiscal year, to date.
     * The year-to-date budget is the annual budget split evenly by month (to the cent), for each
     * month started so far. Variance is favourable when positive: income over budget, or spending
     * under it.
     */
    public function budgetVsActual(Community $community, FiscalYear $fiscalYear, CarbonImmutable $through): ReportTable
    {
        $currency = $community->currency;
        $yearStart = CarbonImmutable::parse($fiscalYear->starts_on->toDateString());
        $yearEnd = CarbonImmutable::parse($fiscalYear->ends_on->toDateString());
        $through = $through->greaterThan($yearEnd) ? $yearEnd : $through;
        $monthsElapsed = $through->lessThan($yearStart) ? 0 : min(12, (int) $yearStart->diffInMonths($through->startOfMonth()) + 1);

        $budgets = BudgetLine::query()->withoutGlobalScopes()->where('fiscal_year_id', $fiscalYear->id)->pluck('annual_cents', 'account_id')->map(fn ($cents) => (int) $cents)->all();
        $actuals = $this->accountBalances->forCommunity($community, $through, $yearStart);
        $accounts = $this->accounts($community);

        $table = new ReportTable(
            __('Budget vs actual'),
            __('Fiscal :year, through :date', ['year' => $fiscalYear->label(), 'date' => $through->toFormattedDateString()]),
            [__('Annual budget'), __('Budget to date'), __('Actual to date'), __('Variance')],
        );

        $net = array_fill(0, 4, 0);

        foreach ([[AccountType::Income, __('Income')], [AccountType::Expense, __('Expenses')]] as [$type, $heading]) {
            $table->section($heading);
            $sectionTotals = array_fill(0, 4, 0);

            foreach ($accounts->where('type', $type) as $account) {
                $annual = $budgets[$account->id] ?? 0;
                $actual = ($actuals->get($account->id) ?? Money::zero($currency))->cents;

                if ($annual === 0 && $actual === 0) {
                    continue;
                }

                $toDate = $this->budgetToDate($annual, $monthsElapsed, $currency);
                $variance = $type === AccountType::Income ? $actual - $toDate : $toDate - $actual;
                $row = [$annual, $toDate, $actual, $variance];

                foreach ($row as $index => $cents) {
                    $sectionTotals[$index] += $cents;
                }

                $table->line($account->label(), array_map(fn (int $cents) => Money::of($cents, $currency), $row));
            }

            $table->total(__('Total :section', ['section' => mb_strtolower($heading)]), array_map(fn (int $cents) => Money::of($cents, $currency), $sectionTotals));

            foreach ([0, 1, 2] as $index) {
                $net[$index] += $type === AccountType::Income ? $sectionTotals[$index] : -$sectionTotals[$index];
            }

            $net[3] += $sectionTotals[3];
        }

        return $table->total(__('Net'), array_map(fn (int $cents) => Money::of($cents, $currency), $net), grand: true);
    }

    /**
     * Every posted line in the period, account by account, with opening and running balances
     * (signed by each account's normal side).
     */
    public function generalLedger(Community $community, CarbonImmutable $from, CarbonImmutable $to, ?int $accountId = null): ReportTable
    {
        $currency = $community->currency;
        $openings = $this->accountBalances->forCommunity($community, $from->subDay());
        $accounts = $this->accounts($community)->when($accountId !== null, fn (Collection $accounts) => $accounts->where('id', $accountId));

        $lines = LedgerEntry::query()->withoutGlobalScopes()
            ->whereIn('account_id', $accounts->pluck('id'))
            ->whereDate('posted_on', '>=', $from->toDateString())
            ->whereDate('posted_on', '<=', $to->toDateString())
            ->with('journalEntry')
            ->orderBy('posted_on')->orderBy('id')
            ->get()
            ->groupBy('account_id');

        $table = new ReportTable(__('General ledger'), $this->period($from, $to), [__('Debit'), __('Credit'), __('Balance')], __('Date · description'));

        foreach ($accounts as $account) {
            $opening = $openings->get($account->id) ?? Money::zero($currency);
            $accountLines = $lines->get($account->id, collect());

            if ($opening->isZero() && $accountLines->isEmpty()) {
                continue;
            }

            $table->section($account->label());
            $table->line(__('Opening balance'), [null, null, $opening]);

            $running = $opening;
            $debits = 0;
            $credits = 0;

            foreach ($accountLines as $line) {
                $running = $running->plus(Money::of($account->type->balanceFrom($line->debit_cents, $line->credit_cents), $currency));
                $debits += $line->debit_cents;
                $credits += $line->credit_cents;

                $table->line(
                    $line->posted_on->toDateString().' · '.$line->journalEntry->memo,
                    [$line->debit_cents > 0 ? Money::of($line->debit_cents, $currency) : null, $line->credit_cents > 0 ? Money::of($line->credit_cents, $currency) : null, $running],
                );
            }

            $table->total(__('Closing balance · :account', ['account' => $account->label()]), [Money::of($debits, $currency), Money::of($credits, $currency), $running]);
        }

        return $table;
    }

    /**
     * @return Collection<int, Account>
     */
    private function accounts(Community $community): Collection
    {
        return Account::query()->withoutGlobalScopes()->where('community_id', $community->id)->orderBy('code')->get();
    }

    /**
     * @param  Collection<int, Account>  $accounts
     * @param  Collection<int, Money>  $amounts
     */
    private function sectionOfMovements(ReportTable $table, string $heading, Collection $accounts, Collection $amounts, string $currency, string $totalLabel): Money
    {
        $table->section($heading);
        $total = Money::zero($currency);

        foreach ($accounts as $account) {
            $amount = $amounts->get($account->id) ?? Money::zero($currency);

            if ($amount->isZero()) {
                continue;
            }

            $total = $total->plus($amount);
            $table->line($account->label(), [$amount]);
        }

        $table->total($totalLabel, [$total]);

        return $total;
    }

    /**
     * @param  Collection<int, Account>  $accounts
     * @param  Collection<int, Money>  $amounts
     */
    private function sum(Collection $accounts, Collection $amounts, string $currency): Money
    {
        return $accounts->reduce(fn (Money $sum, Account $account) => $sum->plus($amounts->get($account->id) ?? Money::zero($currency)), Money::zero($currency));
    }

    private function budgetToDate(int $annualCents, int $monthsElapsed, string $currency): int
    {
        if ($annualCents === 0 || $monthsElapsed === 0) {
            return 0;
        }

        $months = Money::of($annualCents, $currency)->allocate(array_fill(0, 12, 1));

        return array_sum(array_map(fn (Money $month) => $month->cents, array_slice($months, 0, $monthsElapsed)));
    }

    private function period(CarbonImmutable $from, CarbonImmutable $to): string
    {
        return $from->toFormattedDateString().' – '.$to->toFormattedDateString();
    }
}

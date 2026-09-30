<?php

namespace App\Livewire\Finance;

use App\Enums\FinancialReport;
use App\Models\Account;
use App\Models\Community;
use App\Models\Invoice;
use App\Support\Finance\FinancialReports;
use App\Support\Finance\Money;
use App\Support\Finance\ReportTable;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Financial reports')]
class Reports extends Component
{
    public Community $community;

    #[Url]
    public string $report = 'income-statement';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $account = '';

    public function mount(): void
    {
        $this->authorize('viewAny', [Invoice::class, $this->community]);

        $today = CarbonImmutable::now($this->community->timezone);
        $this->from = $this->validDate($this->from) ?? $today->startOfYear()->toDateString();
        $this->to = $this->validDate($this->to) ?? $today->toDateString();
    }

    #[Computed]
    public function reportType(): FinancialReport
    {
        return FinancialReport::tryFrom($this->report) ?? FinancialReport::IncomeStatement;
    }

    #[Computed]
    public function table(): ?ReportTable
    {
        $from = $this->validDate($this->from);
        $to = $this->validDate($this->to);

        if ($from === null || $to === null || $to < $from) {
            return null;
        }

        return app(FinancialReports::class)->build(
            $this->community,
            $this->reportType(),
            CarbonImmutable::parse($from),
            CarbonImmutable::parse($to),
            $this->account !== '' ? $this->accountId() : null,
        );
    }

    /**
     * A quick visual summary above the table, for the two report types that have one — aging
     * receivables as a severity-ordered bar list, or budget vs actual as a favorable/unfavorable
     * comparison. Null for report types with no chart (income statement, balance sheet, general
     * ledger) or when the table itself is unavailable (invalid date range).
     *
     * @return list<array{label: string, value: string, percent: float, color: string}>|null
     */
    #[Computed]
    public function chartBars(): ?array
    {
        $table = $this->table();

        if ($table === null) {
            return null;
        }

        return match ($this->reportType()) {
            FinancialReport::AgedReceivables => $this->agingBars($table),
            FinancialReport::BudgetVsActual => $this->budgetVsActualBars($table),
            default => null,
        };
    }

    /**
     * @return Collection<int, Account>
     */
    #[Computed]
    public function accounts(): Collection
    {
        return $this->community->accounts()->orderBy('code')->get();
    }

    /**
     * @return array<string, string>
     */
    public function exportQuery(string $format): array
    {
        return array_filter([
            'report' => $this->reportType()->value,
            'format' => $format,
            'from' => $this->from,
            'to' => $this->to,
            'account' => $this->reportType() === FinancialReport::GeneralLedger ? $this->account : '',
        ], fn (string $value) => $value !== '');
    }

    public function render(): View
    {
        return view('livewire.finance.reports');
    }

    /**
     * The 6 real aging buckets (the table's 7th "Total" column is the row's own grand total, not
     * a 7th bucket), scaled against whichever bucket is largest and colored as a severity ramp —
     * Credits is a different sign (a net credit balance), not a step on that ladder.
     *
     * @return list<array{label: string, value: string, percent: float, color: string}>
     */
    private function agingBars(ReportTable $table): array
    {
        $buckets = array_map(fn (?int $cents) => $cents ?? 0, array_slice($table->centsFor(__('Total')) ?? [], 0, 6));
        $labels = [__('Current'), __('1–30 days'), __('31–60 days'), __('61–90 days'), __('Over 90 days'), __('Credits')];
        $colors = ['emerald-200', 'emerald-500', 'amber-500', 'red-500', 'red-700', 'zinc-400'];
        $currency = $this->community->currency;
        $max = max(1, ...array_map(abs(...), $buckets));

        return array_map(fn (int $cents, int $index) => [
            'label' => $labels[$index],
            'value' => Money::of($cents, $currency)->format(),
            'percent' => abs($cents) / $max * 100,
            'color' => $colors[$index],
        ], $buckets, array_keys($buckets));
    }

    /**
     * Income and expenses, each shown as actual-to-date against budget-to-date, colored green
     * when favorable and red when unfavorable (budgetVsActual()'s own variance sign already means
     * "favorable" uniformly across both sections).
     *
     * @return list<array{label: string, value: string, percent: float, color: string}>
     */
    private function budgetVsActualBars(ReportTable $table): array
    {
        $currency = $this->community->currency;
        $sections = [__('Income') => __('Total income'), __('Expenses') => __('Total expenses')];

        return array_map(function (string $label, string $totalLabel) use ($table, $currency) {
            [, $toDate, $actual, $variance] = array_map(fn (?int $cents) => $cents ?? 0, $table->centsFor($totalLabel) ?? [0, 0, 0, 0]);
            $percent = $toDate > 0 ? ($actual / $toDate) * 100 : ($actual > 0 ? 100.0 : 0.0);

            return [
                'label' => $label,
                'value' => __(':actual of :budget budgeted', ['actual' => Money::of($actual, $currency)->format(), 'budget' => Money::of($toDate, $currency)->format()]),
                'percent' => min(100.0, $percent),
                'color' => $variance >= 0 ? 'emerald-500' : 'red-500',
            ];
        }, array_keys($sections), array_values($sections));
    }

    private function accountId(): ?int
    {
        return $this->accounts()->firstWhere('id', (int) $this->account)?->id;
    }

    private function validDate(string $value): ?string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4)) ? $value : null;
    }
}

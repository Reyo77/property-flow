<?php

namespace App\Livewire\Finance;

use App\Enums\FinancialReport;
use App\Models\Account;
use App\Models\Community;
use App\Models\Invoice;
use App\Support\Finance\FinancialReports;
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

    private function accountId(): ?int
    {
        return $this->accounts()->firstWhere('id', (int) $this->account)?->id;
    }

    private function validDate(string $value): ?string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4)) ? $value : null;
    }
}

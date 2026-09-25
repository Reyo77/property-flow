<?php

namespace App\Livewire\Finance;

use App\Enums\SystemAccount;
use App\Models\Community;
use App\Models\Invoice;
use App\Models\Unit;
use App\Support\Finance\AccountBalances;
use App\Support\Finance\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Finance')]
class Overview extends Component
{
    public Community $community;

    public function mount(): void
    {
        $this->authorize('viewAny', [Invoice::class, $this->community]);
    }

    #[Computed]
    public function cash(): Money
    {
        return app(AccountBalances::class)->of($this->community, SystemAccount::Cash);
    }

    #[Computed]
    public function receivables(): Money
    {
        return app(AccountBalances::class)->of($this->community, SystemAccount::Receivables);
    }

    /**
     * @return array{count: int, amount: Money}
     */
    #[Computed]
    public function overdue(): array
    {
        $today = CarbonImmutable::now($this->community->timezone)->toDateString();

        $invoices = $this->community->invoices()->withPaid()->whereNull('voided_at')->whereDate('due_on', '<', $today)->get()
            ->filter(fn (Invoice $invoice) => $invoice->balanceCents() > 0);

        return [
            'count' => $invoices->count(),
            'amount' => Money::of($invoices->sum(fn (Invoice $invoice) => $invoice->balanceCents()), $this->community->currency),
        ];
    }

    /**
     * Units that owe money (largest first), then units with a credit.
     *
     * @return Collection<int, array{unit: Unit, balance: Money}>
     */
    #[Computed]
    public function unitBalances(): Collection
    {
        $balances = app(AccountBalances::class)->unitBalances($this->community);
        $units = $this->community->units()->with('building')->whereIn('id', $balances->keys())->get()->keyBy('id');

        return $balances
            ->map(fn (Money $balance, int $unitId) => ['unit' => $units->get($unitId), 'balance' => $balance])
            ->filter(fn (array $row) => $row['unit'] !== null)
            ->sortByDesc(fn (array $row) => $row['balance']->cents)
            ->values();
    }

    public function render(): View
    {
        return view('livewire.finance.overview');
    }
}

<?php

namespace App\Livewire\Finance;

use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Community;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Unit;
use App\Support\Finance\Money;
use App\Support\Finance\UnitLedger;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * A unit's account: its balance, open invoices and a statement with a running balance.
 * Finance staff reach it from invoices and payments; the unit's residents from their dashboard.
 */
#[Title('Account')]
class UnitAccount extends Component
{
    use InteractsWithCurrentUser;

    public Community $community;

    public Unit $unit;

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function mount(): void
    {
        $this->authorize('viewLedger', $this->unit);

        if ($this->from === '') {
            $this->from = CarbonImmutable::now($this->community->timezone)->subMonths(3)->startOfMonth()->toDateString();
        }
    }

    #[Computed]
    public function balance(): Money
    {
        return app(UnitLedger::class)->balance($this->unit);
    }

    /**
     * @return Collection<int, Invoice>
     */
    #[Computed]
    public function openInvoices(): Collection
    {
        return Invoice::query()->where('unit_id', $this->unit->id)->withPaid()->open()->get()
            ->filter(fn (Invoice $invoice) => $invoice->balanceCents() > 0)
            ->values();
    }

    /**
     * @return SupportCollection<int, array{entry: LedgerEntry, balance: Money}>
     */
    #[Computed]
    public function statement(): SupportCollection
    {
        return app(UnitLedger::class)->statement($this->unit, $this->parsedDate($this->from), $this->parsedDate($this->to));
    }

    #[Computed]
    public function openingBalance(): Money
    {
        $from = $this->parsedDate($this->from);

        return $from === null ? Money::zero() : app(UnitLedger::class)->balance($this->unit, $from->subDay());
    }

    /**
     * @return Collection<int, Payment>
     */
    #[Computed]
    public function payments(): Collection
    {
        return Payment::query()->where('unit_id', $this->unit->id)->latest('received_on')->latest('id')->limit(10)->get();
    }

    #[Computed]
    public function canManage(): bool
    {
        return $this->currentUser()->can('create', [Payment::class, $this->community]);
    }

    public function render(): View
    {
        return view('livewire.finance.unit-account');
    }

    private function parsedDate(string $value): ?CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        return CarbonImmutable::createFromFormat('Y-m-d', $value)?->startOfDay() ?: null;
    }
}

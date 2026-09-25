<?php

namespace App\Livewire\Governance;

use App\Enums\ArchitecturalRequestStatus;
use App\Enums\DocumentVisibility;
use App\Enums\Permission;
use App\Enums\SystemAccount;
use App\Enums\VendorBillStatus;
use App\Enums\ViolationStatus;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\ArchitecturalRequest;
use App\Models\Ballot;
use App\Models\Community;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Meeting;
use App\Models\VendorBill;
use App\Models\Violation;
use App\Support\Finance\AccountBalances;
use App\Support\Finance\FinancialReports;
use App\Support\Finance\FiscalYears;
use App\Support\Finance\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Everything waiting on the board in one place: decisions to make, the money, what's coming
 * up, and the board's own documents.
 */
#[Title('Board portal')]
class BoardPortal extends Component
{
    use InteractsWithCurrentUser;

    public Community $community;

    public function mount(): void
    {
        $this->authorize('create', [Ballot::class, $this->community]);
    }

    /**
     * @return Collection<int, VendorBill>
     */
    #[Computed]
    public function billsToApprove(): Collection
    {
        $user = $this->currentUser();

        return $this->community->vendorBills()->where('status', VendorBillStatus::Pending)->with(['vendor', 'community'])->oldest('billed_on')->get()
            ->filter(fn (VendorBill $bill) => $user->can('approve', $bill))->values();
    }

    /**
     * @return Collection<int, ArchitecturalRequest>
     */
    #[Computed]
    public function renovationRequests(): Collection
    {
        return $this->community->architecturalRequests()->whereIn('status', [ArchitecturalRequestStatus::Submitted, ArchitecturalRequestStatus::UnderReview])->with('unit.building')->oldest()->get();
    }

    /**
     * Open violations that have run out of automatic steps and need the board's decision.
     *
     * @return Collection<int, Violation>
     */
    #[Computed]
    public function violationsForReview(): Collection
    {
        return $this->community->violations()->where('status', ViolationStatus::Open)->whereNull('next_action_on')->with(['rule', 'unit.building'])->get();
    }

    /**
     * @return Collection<int, Ballot>
     */
    #[Computed]
    public function ballotsToClose(): Collection
    {
        return $this->community->ballots()->whereNotNull('published_at')->whereNull('closed_at')->where('closes_at', '<=', now())->get();
    }

    /**
     * @return array{cash: Money, receivables: Money, payables: Money, overdue: Money, income: Money, expenses: Money, net: Money, year: string}|null
     */
    #[Computed]
    public function financials(): ?array
    {
        if (! $this->currentUser()->hasCompanyPermission(Permission::ViewFinance)) {
            return null;
        }

        $balances = app(AccountBalances::class);
        $today = CarbonImmutable::now($this->community->timezone);
        $year = app(FiscalYears::class)->covering($this->community, $today);
        $statement = app(FinancialReports::class)->incomeStatement($this->community, CarbonImmutable::parse($year->starts_on->toDateString()), $today);
        $currency = $this->community->currency;

        $overdue = $this->community->invoices()->withPaid()->whereNull('voided_at')->whereDate('due_on', '<', $today->toDateString())->get()
            ->sum(fn (Invoice $invoice) => max(0, $invoice->balanceCents()));

        return [
            'cash' => $balances->of($this->community, SystemAccount::Cash),
            'receivables' => $balances->of($this->community, SystemAccount::Receivables),
            'payables' => $balances->of($this->community, SystemAccount::Payables),
            'overdue' => Money::of((int) $overdue, $currency),
            'income' => Money::of((int) ($statement->centsFor(__('Total income'))[0] ?? 0), $currency),
            'expenses' => Money::of((int) ($statement->centsFor(__('Total expenses'))[0] ?? 0), $currency),
            'net' => Money::of((int) ($statement->centsFor(__('Net income'))[0] ?? 0), $currency),
            'year' => $year->label(),
        ];
    }

    /**
     * @return Collection<int, Meeting>
     */
    #[Computed]
    public function upcomingMeetings(): Collection
    {
        return $this->community->meetings()->whereNull('closed_at')->where('starts_at', '>=', now()->subDay())->orderBy('starts_at')->limit(5)->get();
    }

    /**
     * @return Collection<int, Document>
     */
    #[Computed]
    public function boardDocuments(): Collection
    {
        return $this->community->documents()->where('visibility', DocumentVisibility::Board)->latest()->limit(8)->get();
    }

    public function render(): View
    {
        return view('livewire.governance.board-portal');
    }
}

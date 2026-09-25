<?php

namespace App\Livewire\Finance;

use App\Actions\Finance\DecideVendorBill;
use App\Actions\Finance\PayVendorBill;
use App\Actions\Finance\SubmitVendorBill;
use App\Concerns\FinanceValidationRules;
use App\Enums\AccountType;
use App\Enums\PaymentMethod;
use App\Enums\VendorBillStatus;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Account;
use App\Models\Community;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Support\Finance\ChartOfAccounts;
use App\Support\Finance\Money;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use LogicException;

#[Title('Vendor bills')]
class Bills extends Component
{
    use FinanceValidationRules, InteractsWithCurrentUser, WithPagination;

    public Community $community;

    #[Url]
    public string $status = '';

    public string $vendor_id = '';

    public string $account_id = '';

    public string $description = '';

    public string $vendor_reference = '';

    public string $amount = '';

    public string $billed_on = '';

    public string $due_on = '';

    public ?int $actingOnId = null;

    public string $decision_notes = '';

    public string $payment_method = '';

    public string $paid_on = '';

    public string $payment_reference = '';

    public function mount(ChartOfAccounts $chartOfAccounts): void
    {
        $this->authorize('viewAny', [VendorBill::class, $this->community]);

        $chartOfAccounts->ensureFor($this->community);
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function canCreate(): bool
    {
        return $this->currentUser()->can('create', [VendorBill::class, $this->community]);
    }

    /**
     * @return LengthAwarePaginator<int, VendorBill>
     */
    #[Computed]
    public function bills(): LengthAwarePaginator
    {
        $query = $this->community->vendorBills()->with(['vendor', 'account', 'community'])->latest('billed_on')->latest('id');

        if (VendorBillStatus::tryFrom($this->status) !== null) {
            $query->where('status', $this->status);
        }

        return $query->paginate(25);
    }

    #[Computed]
    public function outstanding(): Money
    {
        return Money::of((int) $this->community->vendorBills()->where('status', VendorBillStatus::Approved)->sum('amount_cents'), $this->community->currency);
    }

    /**
     * @return Collection<int, Vendor>
     */
    #[Computed]
    public function vendors(): Collection
    {
        return Vendor::query()->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Account>
     */
    #[Computed]
    public function expenseAccounts(): Collection
    {
        return $this->community->accounts()->where('type', AccountType::Expense)->where('is_active', true)->orderBy('code')->get();
    }

    #[Computed]
    public function actingOn(): ?VendorBill
    {
        return $this->actingOnId === null ? null : $this->community->vendorBills()->with(['vendor', 'community'])->find($this->actingOnId);
    }

    public function create(): void
    {
        $this->authorize('create', [VendorBill::class, $this->community]);

        $this->resetValidation();
        $this->reset('vendor_id', 'account_id', 'description', 'vendor_reference', 'amount');
        $today = CarbonImmutable::now($this->community->timezone);
        $this->billed_on = $today->toDateString();
        $this->due_on = $today->addDays(30)->toDateString();

        Flux::modal('bill-form')->show();
    }

    public function save(SubmitVendorBill $submitVendorBill): void
    {
        $this->authorize('create', [VendorBill::class, $this->community]);

        $validated = $this->validate($this->vendorBillRules($this->community));

        try {
            $bill = $submitVendorBill->handle(
                $this->community,
                Vendor::query()->findOrFail((int) $validated['vendor_id']),
                $this->community->accounts()->findOrFail((int) $validated['account_id']),
                Money::parse($validated['amount'], $this->community->currency),
                CarbonImmutable::parse($validated['billed_on']),
                CarbonImmutable::parse($validated['due_on']),
                $validated['description'],
                $validated['vendor_reference'] ?: null,
                $this->currentUser(),
            );
        } catch (InvalidArgumentException $exception) {
            $this->addError('amount', $exception->getMessage());

            return;
        }

        Flux::modal('bill-form')->close();
        Flux::toast(variant: 'success', text: __('Bill :number submitted for approval.', ['number' => $bill->displayNumber()]));

        unset($this->bills);
    }

    public function review(int $billId): void
    {
        $bill = $this->community->vendorBills()->findOrFail($billId);

        $this->authorize('approve', $bill);

        $this->resetValidation();
        $this->actingOnId = $bill->id;
        $this->decision_notes = '';
        unset($this->actingOn);

        Flux::modal('bill-decision')->show();
    }

    public function approve(DecideVendorBill $decideVendorBill): void
    {
        $bill = $this->community->vendorBills()->findOrFail($this->actingOnId);

        $this->authorize('approve', $bill);

        $this->validate(['decision_notes' => ['nullable', 'string', 'max:255']]);

        try {
            $decideVendorBill->approve($bill, $this->currentUser(), $this->decision_notes ?: null);
        } catch (LogicException $exception) {
            $this->addError('decision_notes', $exception->getMessage());

            return;
        }

        $this->finishAction('bill-decision', __('Bill approved.'));
    }

    public function reject(DecideVendorBill $decideVendorBill): void
    {
        $bill = $this->community->vendorBills()->findOrFail($this->actingOnId);

        $this->authorize('approve', $bill);

        $this->validate(['decision_notes' => ['required', 'string', 'max:255']], ['decision_notes.required' => __('Say why the bill is rejected.')]);

        try {
            $decideVendorBill->reject($bill, $this->currentUser(), $this->decision_notes);
        } catch (LogicException $exception) {
            $this->addError('decision_notes', $exception->getMessage());

            return;
        }

        $this->finishAction('bill-decision', __('Bill rejected.'));
    }

    public function startPayment(int $billId): void
    {
        $bill = $this->community->vendorBills()->findOrFail($billId);

        $this->authorize('pay', $bill);

        $this->resetValidation();
        $this->actingOnId = $bill->id;
        $this->payment_method = PaymentMethod::Cheque->value;
        $this->paid_on = CarbonImmutable::now($this->community->timezone)->toDateString();
        $this->payment_reference = '';
        unset($this->actingOn);

        Flux::modal('bill-payment')->show();
    }

    public function pay(PayVendorBill $payVendorBill): void
    {
        $bill = $this->community->vendorBills()->findOrFail($this->actingOnId);

        $this->authorize('pay', $bill);

        $validated = $this->validate($this->billPaymentRules());

        try {
            $payVendorBill->handle($bill, PaymentMethod::from($validated['payment_method']), CarbonImmutable::parse($validated['paid_on']), $validated['payment_reference'] ?: null, $this->currentUser());
        } catch (LogicException $exception) {
            $this->addError('paid_on', $exception->getMessage());

            return;
        }

        $this->finishAction('bill-payment', __('Bill marked as paid.'));
    }

    public function render(): View
    {
        return view('livewire.finance.bills');
    }

    private function finishAction(string $modal, string $message): void
    {
        Flux::modal($modal)->close();
        Flux::toast(variant: 'success', text: $message);

        $this->actingOnId = null;
        unset($this->bills, $this->outstanding, $this->actingOn);
    }
}

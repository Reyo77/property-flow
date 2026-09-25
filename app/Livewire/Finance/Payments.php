<?php

namespace App\Livewire\Finance;

use App\Actions\Finance\RecordPayment;
use App\Actions\Finance\ReversePayment;
use App\Concerns\FinanceValidationRules;
use App\Enums\PaymentMethod;
use App\Enums\PaymentReversalReason;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Community;
use App\Models\Payment;
use App\Models\Unit;
use App\Support\Finance\Money;
use App\Support\Finance\UnitLedger;
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

#[Title('Payments')]
class Payments extends Component
{
    use FinanceValidationRules, InteractsWithCurrentUser, WithPagination;

    public Community $community;

    #[Url]
    public string $unit = '';

    public string $unit_id = '';

    public string $method = '';

    public string $amount = '';

    public string $received_on = '';

    public string $reference = '';

    public string $memo = '';

    public ?int $reversingId = null;

    public string $reversal_reason = '';

    public string $reversed_on = '';

    public function mount(): void
    {
        $this->authorize('viewAny', [Payment::class, $this->community]);
    }

    public function updatedUnit(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function canManage(): bool
    {
        return $this->currentUser()->can('create', [Payment::class, $this->community]);
    }

    /**
     * @return LengthAwarePaginator<int, Payment>
     */
    #[Computed]
    public function payments(): LengthAwarePaginator
    {
        $query = $this->community->payments()->with('unit.building')->withSum('allocations as allocated_cents', 'amount_cents')
            ->latest('received_on')->latest('id');

        if ($this->unit !== '') {
            $query->where('unit_id', (int) $this->unit);
        }

        return $query->paginate(25);
    }

    /**
     * @return Collection<int, Unit>
     */
    #[Computed]
    public function units(): Collection
    {
        return $this->community->units()->with('building')->orderBy('number')->get();
    }

    /**
     * What the unit chosen in the form currently owes, shown as a hint while recording.
     */
    #[Computed]
    public function selectedUnitBalance(): ?Money
    {
        $unit = $this->unit_id === '' ? null : $this->community->units()->find((int) $this->unit_id);

        return $unit === null ? null : app(UnitLedger::class)->balance($unit);
    }

    public function create(?int $unitId = null): void
    {
        $this->authorize('create', [Payment::class, $this->community]);

        $this->resetValidation();
        $this->reset('amount', 'reference', 'memo');
        $this->unit_id = $unitId === null ? '' : (string) $unitId;
        $this->method = PaymentMethod::Cheque->value;
        $this->received_on = CarbonImmutable::now($this->community->timezone)->toDateString();

        Flux::modal('payment-form')->show();
    }

    public function save(RecordPayment $recordPayment): void
    {
        $this->authorize('create', [Payment::class, $this->community]);

        $validated = $this->validate($this->paymentRules($this->community));

        try {
            $payment = $recordPayment->handle(
                $this->community->units()->findOrFail((int) $validated['unit_id']),
                PaymentMethod::from($validated['method']),
                Money::parse($validated['amount'], $this->community->currency),
                CarbonImmutable::parse($validated['received_on']),
                $validated['reference'] ?: null,
                $validated['memo'] ?: null,
                $this->currentUser(),
            );
        } catch (InvalidArgumentException|LogicException $exception) {
            $this->addError('amount', $exception->getMessage());

            return;
        }

        Flux::modal('payment-form')->close();
        Flux::toast(variant: 'success', text: __('Payment :number recorded.', ['number' => $payment->displayNumber()]));

        unset($this->payments);
    }

    public function confirmReverse(int $paymentId): void
    {
        $payment = $this->community->payments()->findOrFail($paymentId);

        $this->authorize('reverse', $payment);

        $this->resetValidation();
        $this->reversingId = $payment->id;
        $this->reversal_reason = PaymentReversalReason::Nsf->value;
        $this->reversed_on = CarbonImmutable::now($this->community->timezone)->toDateString();

        Flux::modal('reverse-payment')->show();
    }

    public function reverse(ReversePayment $reversePayment): void
    {
        $payment = $this->community->payments()->findOrFail($this->reversingId);

        $this->authorize('reverse', $payment);

        $validated = $this->validate($this->paymentReversalRules());

        try {
            $reversePayment->handle($payment, PaymentReversalReason::from($validated['reversal_reason']), CarbonImmutable::parse($validated['reversed_on']), $this->currentUser());
        } catch (LogicException $exception) {
            $this->addError('reversal_reason', $exception->getMessage());

            return;
        }

        Flux::modal('reverse-payment')->close();
        Flux::toast(variant: 'success', text: __('Payment reversed.'));

        $this->reversingId = null;
        unset($this->payments);
    }

    public function render(): View
    {
        return view('livewire.finance.payments');
    }
}

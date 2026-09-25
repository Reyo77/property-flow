<?php

namespace App\Livewire\Finance;

use App\Actions\Finance\IssueInvoice;
use App\Actions\Finance\VoidInvoice;
use App\Concerns\FinanceValidationRules;
use App\Enums\InvoiceStatus;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\ChargeType;
use App\Models\Community;
use App\Models\Invoice;
use App\Models\Unit;
use App\Support\Finance\InvoiceLineData;
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

#[Title('Invoices')]
class Invoices extends Component
{
    use FinanceValidationRules, InteractsWithCurrentUser, WithPagination;

    /**
     * The same "paid so far" figure as {@see Invoice::withPaid()}, usable in a WHERE clause.
     */
    private const string PAID_SQL = '(select coalesce(sum(pa.amount_cents), 0) from payment_allocations pa'
        .' join payments p on p.id = pa.payment_id and p.reversed_at is null'
        .' where pa.invoice_id = invoices.id)';

    public Community $community;

    #[Url]
    public string $status = '';

    #[Url]
    public string $unit = '';

    public string $unit_id = '';

    public string $issued_on = '';

    public string $due_on = '';

    public string $memo = '';

    /**
     * @var list<array{charge_type_id: string, description: string, amount: string}>
     */
    public array $lines = [];

    public ?int $voidingId = null;

    public string $void_reason = '';

    public function mount(): void
    {
        $this->authorize('viewAny', [Invoice::class, $this->community]);
    }

    public function updated(string $property): void
    {
        if ($property === 'status' || $property === 'unit') {
            $this->resetPage();
        }

        if (preg_match('/^lines\.(\d+)\.charge_type_id$/', $property, $matches) === 1) {
            $this->fillLineFromChargeType((int) $matches[1]);
        }
    }

    #[Computed]
    public function canManage(): bool
    {
        return $this->currentUser()->can('create', [Invoice::class, $this->community]);
    }

    /**
     * @return LengthAwarePaginator<int, Invoice>
     */
    #[Computed]
    public function invoices(): LengthAwarePaginator
    {
        $query = $this->community->invoices()->withPaid()->with('unit.building')->latest('issued_on')->latest('id');

        if ($this->unit !== '') {
            $query->where('unit_id', (int) $this->unit);
        }

        match (InvoiceStatus::tryFrom($this->status)) {
            InvoiceStatus::Voided => $query->whereNotNull('voided_at'),
            InvoiceStatus::Paid => $query->whereNull('voided_at')->whereRaw(self::PAID_SQL.' >= total_cents'),
            InvoiceStatus::PartiallyPaid => $query->whereNull('voided_at')->whereRaw(self::PAID_SQL.' between 1 and total_cents - 1'),
            InvoiceStatus::Open => $query->whereNull('voided_at')->whereRaw(self::PAID_SQL.' = 0'),
            null => null,
        };

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
     * @return Collection<int, ChargeType>
     */
    #[Computed]
    public function chargeTypes(): Collection
    {
        return $this->community->chargeTypes()->where('is_active', true)->orderBy('name')->get();
    }

    public function create(): void
    {
        $this->authorize('create', [Invoice::class, $this->community]);

        $this->resetValidation();
        $this->reset('unit_id', 'memo');
        $today = CarbonImmutable::now($this->community->timezone);
        $this->issued_on = $today->toDateString();
        $this->due_on = $today->addDays(30)->toDateString();
        $this->lines = [];
        $this->addLine();

        Flux::modal('invoice-form')->show();
    }

    public function addLine(): void
    {
        $this->lines[] = ['charge_type_id' => '', 'description' => '', 'amount' => ''];
    }

    public function removeLine(int $index): void
    {
        $this->lines = array_values(array_filter($this->lines, fn (int $key) => $key !== $index, ARRAY_FILTER_USE_KEY));
    }

    public function save(IssueInvoice $issueInvoice): void
    {
        $this->authorize('create', [Invoice::class, $this->community]);

        $validated = $this->validate($this->invoiceRules($this->community));

        $chargeTypes = $this->community->chargeTypes()->with('account')->get()->keyBy('id');
        $lines = [];

        foreach ($validated['lines'] as $line) {
            $chargeType = $chargeTypes->get((int) $line['charge_type_id']);

            if ($chargeType === null) {
                continue;
            }

            $lines[] = new InvoiceLineData($line['description'], Money::parse($line['amount'], $this->community->currency), $chargeType->account, $chargeType);
        }

        try {
            $invoice = $issueInvoice->handle(
                $this->community->units()->findOrFail((int) $validated['unit_id']),
                CarbonImmutable::parse($validated['issued_on']),
                CarbonImmutable::parse($validated['due_on']),
                $lines,
                $validated['memo'] ?: null,
                $this->currentUser(),
            );
        } catch (InvalidArgumentException|LogicException $exception) {
            $this->addError('issued_on', $exception->getMessage());

            return;
        }

        Flux::modal('invoice-form')->close();
        Flux::toast(variant: 'success', text: __('Invoice :number issued.', ['number' => $invoice->displayNumber()]));

        unset($this->invoices);
    }

    public function confirmVoid(int $invoiceId): void
    {
        $invoice = $this->community->invoices()->findOrFail($invoiceId);

        $this->authorize('void', $invoice);

        $this->resetValidation();
        $this->voidingId = $invoice->id;
        $this->void_reason = '';

        Flux::modal('void-invoice')->show();
    }

    public function void(VoidInvoice $voidInvoice): void
    {
        $invoice = $this->community->invoices()->findOrFail($this->voidingId);

        $this->authorize('void', $invoice);

        $this->validate(['void_reason' => ['required', 'string', 'max:200']]);

        try {
            $voidInvoice->handle($invoice, CarbonImmutable::now($this->community->timezone), $this->void_reason, $this->currentUser());
        } catch (LogicException $exception) {
            $this->addError('void_reason', $exception->getMessage());

            return;
        }

        Flux::modal('void-invoice')->close();
        Flux::toast(variant: 'success', text: __('Invoice voided.'));

        $this->voidingId = null;
        unset($this->invoices);
    }

    public function render(): View
    {
        return view('livewire.finance.invoices');
    }

    private function fillLineFromChargeType(int $index): void
    {
        if (! isset($this->lines[$index])) {
            return;
        }

        $chargeType = $this->chargeTypes()->firstWhere('id', (int) $this->lines[$index]['charge_type_id']);

        if ($chargeType === null) {
            return;
        }

        if ($this->lines[$index]['description'] === '') {
            $this->lines[$index]['description'] = $chargeType->name;
        }

        if ($this->lines[$index]['amount'] === '' && $chargeType->default_amount_cents !== null) {
            $this->lines[$index]['amount'] = Money::of($chargeType->default_amount_cents)->toDecimal();
        }
    }
}

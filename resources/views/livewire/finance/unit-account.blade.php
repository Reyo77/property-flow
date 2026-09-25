<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Account for unit :unit', ['unit' => $unit->label()]) }}</flux:heading>
            <flux:subheading>{{ $community->name }}</flux:subheading>
        </div>

        <div class="flex gap-2">
            @if ($this->balance->isPositive() && $this->canPayOnline())
                <form method="POST" action="{{ route('communities.units.pay-online', [$community, $unit]) }}">
                    @csrf
                    <flux:button type="submit" variant="primary" icon="credit-card">
                        {{ __('Pay :amount online', ['amount' => $this->balance->format()]) }}
                    </flux:button>
                </form>
            @endif
            <flux:button icon="arrow-down-tray" :href="route('communities.units.statement', [$community, $unit, 'from' => $from, 'to' => $to])">{{ __('Statement PDF') }}</flux:button>
            @if ($this->canManage())
                <flux:button variant="primary" icon="banknotes" :href="route('communities.finance.payments', $community)" wire:navigate>{{ __('Payments') }}</flux:button>
            @endif
        </div>
    </div>

    @if (session('payment_status'))
        <flux:callout variant="success" icon="check-circle" :heading="session('payment_status')" />
    @endif

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:text>{{ $this->balance->isNegative() ? __('Credit on account') : __('Balance owing') }}</flux:text>
            <flux:heading size="xl" data-test="unit-balance">{{ $this->balance->isNegative() ? $this->balance->negate()->format() : $this->balance->format() }}</flux:heading>
        </div>
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:text>{{ __('Open invoices') }}</flux:text>
            <flux:heading size="xl">{{ $this->openInvoices->count() }}</flux:heading>
        </div>
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:text>{{ __('Overdue') }}</flux:text>
            <flux:heading size="xl">{{ App\Support\Finance\Money::of($this->openInvoices->filter->isOverdue()->sum(fn ($invoice) => $invoice->balanceCents()))->format() }}</flux:heading>
        </div>
    </div>

    @if ($this->openInvoices->isNotEmpty())
        <div class="space-y-3">
            <flux:heading size="lg">{{ __('Open invoices') }}</flux:heading>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Number') }}</flux:table.column>
                    <flux:table.column>{{ __('Due') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Total') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Balance') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->openInvoices as $invoice)
                        <flux:table.row :key="$invoice->id">
                            <flux:table.cell variant="strong">{{ $invoice->displayNumber() }} <flux:text size="sm">{{ $invoice->memo }}</flux:text></flux:table.cell>
                            <flux:table.cell>
                                {{ $invoice->due_on->toFormattedDateString() }}
                                @if ($invoice->isOverdue())
                                    <flux:badge size="sm" color="red">{{ __('Overdue') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">{{ App\Support\Finance\Money::of($invoice->total_cents)->format() }}</flux:table.cell>
                            <flux:table.cell align="end">{{ App\Support\Finance\Money::of($invoice->balanceCents())->format() }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    @endif

    <div class="space-y-3">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <flux:heading size="lg">{{ __('Statement') }}</flux:heading>
            <div class="flex gap-3">
                <flux:input wire:model.live="from" type="date" :label="__('From')" />
                <flux:input wire:model.live="to" type="date" :label="__('To')" />
            </div>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Description') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Charges') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Payments & credits') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Balance') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                <flux:table.row>
                    <flux:table.cell></flux:table.cell>
                    <flux:table.cell variant="strong">{{ __('Opening balance') }}</flux:table.cell>
                    <flux:table.cell></flux:table.cell>
                    <flux:table.cell></flux:table.cell>
                    <flux:table.cell align="end">{{ $this->openingBalance->format() }}</flux:table.cell>
                </flux:table.row>
                @foreach ($this->statement as $row)
                    <flux:table.row :key="$row['entry']->id">
                        <flux:table.cell>{{ $row['entry']->posted_on->toFormattedDateString() }}</flux:table.cell>
                        <flux:table.cell>{{ $row['entry']->journalEntry->memo }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $row['entry']->debit_cents > 0 ? App\Support\Finance\Money::of($row['entry']->debit_cents)->format() : '' }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $row['entry']->credit_cents > 0 ? App\Support\Finance\Money::of($row['entry']->credit_cents)->format() : '' }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $row['balance']->format() }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>

    @if ($this->payments->isNotEmpty())
        <div class="space-y-3">
            <flux:heading size="lg">{{ __('Recent payments') }}</flux:heading>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Receipt') }}</flux:table.column>
                    <flux:table.column>{{ __('Received') }}</flux:table.column>
                    <flux:table.column>{{ __('Method') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Amount') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->payments as $payment)
                        <flux:table.row :key="$payment->id">
                            <flux:table.cell variant="strong">
                                {{ $payment->displayNumber() }}
                                @if ($payment->isReversed())
                                    <flux:badge size="sm" color="red">{{ $payment->reversal_reason?->label() }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $payment->received_on->toFormattedDateString() }}</flux:table.cell>
                            <flux:table.cell>{{ $payment->method->label() }}</flux:table.cell>
                            <flux:table.cell align="end">{{ App\Support\Finance\Money::of($payment->amount_cents)->format() }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button size="sm" variant="ghost" icon="arrow-down-tray" :href="route('communities.payments.receipt', [$community, $payment])">{{ __('Receipt') }}</flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    @endif
</section>

<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Payments') }}</flux:heading>
            <flux:subheading>{{ $community->name }}</flux:subheading>
        </div>

        @if ($this->canManage())
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Record payment') }}</flux:button>
        @endif
    </div>

    @include('livewire.finance.partials.nav')

    <flux:select wire:model.live="unit" class="max-w-64">
        <flux:select.option value="">{{ __('All units') }}</flux:select.option>
        @foreach ($this->units as $option)
            <flux:select.option :value="$option->id">{{ $option->label() }}</flux:select.option>
        @endforeach
    </flux:select>

    @if ($this->payments->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <flux:heading>{{ __('No payments') }}</flux:heading>
        </div>
    @else
        <flux:table :paginate="$this->payments">
            <flux:table.columns>
                <flux:table.column>{{ __('Receipt') }}</flux:table.column>
                <flux:table.column>{{ __('Unit') }}</flux:table.column>
                <flux:table.column>{{ __('Received') }}</flux:table.column>
                <flux:table.column>{{ __('Method') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Amount') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Unapplied') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->payments as $payment)
                    <flux:table.row :key="$payment->id">
                        <flux:table.cell variant="strong">{{ $payment->displayNumber() }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:link :href="route('communities.units.account', [$community, $payment->unit])" wire:navigate>{{ $payment->unit->label() }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $payment->received_on->toFormattedDateString() }}</flux:table.cell>
                        <flux:table.cell>
                            {{ $payment->method->label() }}
                            @if ($payment->reference)
                                <flux:text size="sm">{{ $payment->reference }}</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">{{ App\Support\Finance\Money::of($payment->amount_cents)->format() }}</flux:table.cell>
                        <flux:table.cell align="end">
                            {{ $payment->isReversed() ? '—' : App\Support\Finance\Money::of($payment->amount_cents - (int) $payment->allocated_cents)->format() }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($payment->isReversed())
                                <flux:badge size="sm" color="red">{{ $payment->reversal_reason?->label() }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="green">{{ __('Received') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-1">
                                <flux:button size="sm" variant="ghost" icon="arrow-down-tray" :href="route('communities.payments.receipt', [$community, $payment])" :aria-label="__('Download receipt')" />
                                @if (! $payment->isReversed())
                                    @can('reverse', $payment)
                                        <flux:button size="sm" variant="ghost" wire:click="confirmReverse({{ $payment->id }})">{{ __('Reverse') }}</flux:button>
                                    @endcan
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="payment-form" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ __('Record payment') }}</flux:heading>

            <flux:select wire:model.live="unit_id" :label="__('Unit')">
                <flux:select.option value="">{{ __('Choose a unit') }}</flux:select.option>
                @foreach ($this->units as $option)
                    <flux:select.option :value="$option->id">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            @if ($this->selectedUnitBalance)
                <flux:text>{{ __('Current balance: :amount', ['amount' => $this->selectedUnitBalance->format()]) }}</flux:text>
            @endif

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="amount" :label="__('Amount')" placeholder="0.00" inputmode="decimal" required />
                <flux:input wire:model="received_on" :label="__('Received on')" type="date" required />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:select wire:model="method" :label="__('Method')">
                    @foreach (App\Enums\PaymentMethod::manual() as $option)
                        <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="reference" :label="__('Reference (optional)')" :placeholder="__('Cheque #, transfer ID')" />
            </div>

            <flux:input wire:model="memo" :label="__('Memo (optional)')" />

            <flux:text size="sm">{{ __('Applied to the oldest open invoices first; anything left over stays on the account as a credit.') }}</flux:text>

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Record payment') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="reverse-payment" class="w-full max-w-md">
        <form wire:submit="reverse" class="space-y-5">
            <flux:heading size="lg">{{ __('Reverse payment') }}</flux:heading>
            <flux:text>{{ __('The invoices it paid will reopen. The original payment stays on the ledger.') }}</flux:text>

            <flux:select wire:model="reversal_reason" :label="__('Reason')">
                @foreach (App\Enums\PaymentReversalReason::cases() as $option)
                    <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="reversed_on" :label="__('Date')" type="date" required />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="danger">{{ __('Reverse payment') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>

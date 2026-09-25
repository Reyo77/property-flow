<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Invoices') }}</flux:heading>
            <flux:subheading>{{ $community->name }}</flux:subheading>
        </div>

        @if ($this->canManage())
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('New invoice') }}</flux:button>
        @endif
    </div>

    @include('livewire.finance.partials.nav')

    <div class="flex flex-wrap gap-3">
        <flux:select wire:model.live="status" class="max-w-48">
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            @foreach (App\Enums\InvoiceStatus::cases() as $option)
                <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="unit" class="max-w-64">
            <flux:select.option value="">{{ __('All units') }}</flux:select.option>
            @foreach ($this->units as $option)
                <flux:select.option :value="$option->id">{{ $option->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @if ($this->invoices->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <flux:heading>{{ __('No invoices') }}</flux:heading>
        </div>
    @else
        <flux:table :paginate="$this->invoices">
            <flux:table.columns>
                <flux:table.column>{{ __('Number') }}</flux:table.column>
                <flux:table.column>{{ __('Unit') }}</flux:table.column>
                <flux:table.column>{{ __('Issued') }}</flux:table.column>
                <flux:table.column>{{ __('Due') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Total') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Balance') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->invoices as $invoice)
                    @php($invoiceStatus = $invoice->status())
                    <flux:table.row :key="$invoice->id">
                        <flux:table.cell variant="strong">
                            {{ $invoice->displayNumber() }}
                            @if ($invoice->memo)
                                <flux:text size="sm">{{ $invoice->memo }}</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:link :href="route('communities.units.account', [$community, $invoice->unit])" wire:navigate>{{ $invoice->unit->label() }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $invoice->issued_on->toFormattedDateString() }}</flux:table.cell>
                        <flux:table.cell>
                            {{ $invoice->due_on->toFormattedDateString() }}
                            @if ($invoice->isOverdue())
                                <flux:badge size="sm" color="red">{{ __('Overdue') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">{{ App\Support\Finance\Money::of($invoice->total_cents)->format() }}</flux:table.cell>
                        <flux:table.cell align="end">{{ App\Support\Finance\Money::of($invoice->balanceCents())->format() }}</flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" :color="$invoiceStatus->color()">{{ $invoiceStatus->label() }}</flux:badge></flux:table.cell>
                        <flux:table.cell align="end">
                            @if (! $invoice->isVoided() && $invoice->paidCents() === 0)
                                @can('void', $invoice)
                                    <flux:button size="sm" variant="ghost" wire:click="confirmVoid({{ $invoice->id }})">{{ __('Void') }}</flux:button>
                                @endcan
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="invoice-form" class="w-full max-w-2xl">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ __('New invoice') }}</flux:heading>

            <flux:select wire:model="unit_id" :label="__('Unit')">
                <flux:select.option value="">{{ __('Choose a unit') }}</flux:select.option>
                @foreach ($this->units as $option)
                    <flux:select.option :value="$option->id">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="issued_on" :label="__('Issued on')" type="date" required />
                <flux:input wire:model="due_on" :label="__('Due on')" type="date" required />
            </div>

            <div class="space-y-3">
                <flux:heading>{{ __('Lines') }}</flux:heading>
                @if ($this->chargeTypes->isEmpty())
                    <flux:text>{{ __('Add a charge type under Accounts & charges first.') }}</flux:text>
                @endif
                @foreach ($lines as $index => $line)
                    <div class="grid grid-cols-12 items-start gap-2" wire:key="line-{{ $index }}">
                        <div class="col-span-4">
                            <flux:select wire:model.live="lines.{{ $index }}.charge_type_id" :aria-label="__('Charge type')">
                                <flux:select.option value="">{{ __('Charge type') }}</flux:select.option>
                                @foreach ($this->chargeTypes as $chargeType)
                                    <flux:select.option :value="$chargeType->id">{{ $chargeType->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="lines.{{ $index }}.charge_type_id" />
                        </div>
                        <div class="col-span-5">
                            <flux:input wire:model="lines.{{ $index }}.description" :placeholder="__('Description')" :aria-label="__('Description')" />
                            <flux:error name="lines.{{ $index }}.description" />
                        </div>
                        <div class="col-span-2">
                            <flux:input wire:model="lines.{{ $index }}.amount" placeholder="0.00" :aria-label="__('Amount')" inputmode="decimal" />
                            <flux:error name="lines.{{ $index }}.amount" />
                        </div>
                        <div class="col-span-1">
                            @if (count($lines) > 1)
                                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="removeLine({{ $index }})" :aria-label="__('Remove line')" />
                            @endif
                        </div>
                    </div>
                @endforeach
                <flux:error name="lines" />
                <flux:button size="sm" variant="ghost" icon="plus" wire:click="addLine">{{ __('Add line') }}</flux:button>
            </div>

            <flux:input wire:model="memo" :label="__('Memo (optional)')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Issue invoice') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="void-invoice" class="w-full max-w-md">
        <form wire:submit="void" class="space-y-5">
            <flux:heading size="lg">{{ __('Void invoice') }}</flux:heading>
            <flux:text>{{ __('Voiding posts a reversing entry; the original stays on the ledger.') }}</flux:text>
            <flux:input wire:model="void_reason" :label="__('Reason')" required />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="danger">{{ __('Void invoice') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>

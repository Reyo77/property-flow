<section class="w-full space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Billing') }}</flux:heading>
        <flux:subheading>{{ $community->name }}</flux:subheading>
    </div>

    @include('livewire.finance.partials.nav')

    <div class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <flux:heading size="lg">{{ __('Recurring charges') }}</flux:heading>
                <flux:text>{{ __('Billed automatically on the 1st of each month. About :total per month.', ['total' => $this->monthlyTotal->format()]) }}</flux:text>
            </div>
            @if ($this->canManage())
                <div class="flex flex-wrap items-end gap-2">
                    <flux:input wire:model="run_month" type="month" size="sm" :aria-label="__('Month to bill')" />
                    <flux:button size="sm" icon="play" wire:click="runBilling" wire:confirm="{{ __('Issue this month\'s invoices now? Units already billed are skipped.') }}">{{ __('Run billing') }}</flux:button>
                    <flux:button size="sm" variant="primary" icon="plus" wire:click="createCharge">{{ __('Add charge') }}</flux:button>
                </div>
            @endif
        </div>

        @if ($this->charges->isEmpty())
            <div class="rounded-xl border border-dashed border-zinc-300 p-6 text-center dark:border-zinc-600">
                <flux:text>{{ __('No recurring charges. Add monthly fees here and they are billed to every unit each month.') }}</flux:text>
            </div>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Charge') }}</flux:table.column>
                    <flux:table.column>{{ __('Billed to') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Amount') }}</flux:table.column>
                    <flux:table.column>{{ __('Period') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->charges as $charge)
                        <flux:table.row :key="$charge->id">
                            <flux:table.cell variant="strong">
                                {{ $charge->description }}
                                @unless ($charge->is_active)
                                    <flux:badge size="sm">{{ __('Paused') }}</flux:badge>
                                @endunless
                                <flux:text size="sm">{{ $charge->chargeType->name }}</flux:text>
                            </flux:table.cell>
                            <flux:table.cell>{{ $charge->unit ? __('Unit :unit', ['unit' => $charge->unit->label()]) : $charge->method->label() }}</flux:table.cell>
                            <flux:table.cell align="end">
                                {{ App\Support\Finance\Money::of($charge->amount_cents)->format() }}
                                <flux:text size="sm">{{ $charge->unit === null && $charge->method === App\Enums\RecurringChargeMethod::UnitFactor ? __('total') : __('per unit') }}</flux:text>
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $charge->starts_on->toFormattedDateString() }} &ndash; {{ $charge->ends_on?->toFormattedDateString() ?? __('ongoing') }}
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                @can('update', $charge)
                                    <flux:button size="sm" variant="ghost" wire:click="editCharge({{ $charge->id }})">{{ __('Edit') }}</flux:button>
                                    <flux:button size="sm" variant="ghost" wire:click="toggleCharge({{ $charge->id }})">{{ $charge->is_active ? __('Pause') : __('Resume') }}</flux:button>
                                @endcan
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </div>

    <form wire:submit="saveSettings" class="space-y-5 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <flux:heading size="lg">{{ __('Settings') }}</flux:heading>

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="billing_due_day" type="number" min="1" max="28" :label="__('Monthly charges due on day')" :disabled="! $this->canManage()" />
            <flux:input wire:model="bill_approval_limit" :label="__('Manager approval limit for vendor bills')" :description="__('Bills above this need a board member.')" inputmode="decimal" :disabled="! $this->canManage()" />
        </div>

        <flux:separator />

        <flux:switch wire:model.live="late_fees_enabled" :label="__('Charge late fees')" :disabled="! $this->canManage()" />

        @if ($late_fees_enabled)
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="grace_days" type="number" min="0" :label="__('Grace period (days after due date)')" :disabled="! $this->canManage()" />
                <flux:input wire:model="minimum_balance" :label="__('Only if at least this much is owing (optional)')" inputmode="decimal" :disabled="! $this->canManage()" />
                <flux:radio.group wire:model.live="fee_kind" :label="__('Fee')" variant="segmented">
                    <flux:radio value="flat" :label="__('Flat amount')" />
                    <flux:radio value="percent" :label="__('Percent of balance')" />
                </flux:radio.group>
                @if ($fee_kind === 'flat')
                    <flux:input wire:model="flat_fee" :label="__('Amount')" placeholder="25.00" inputmode="decimal" :disabled="! $this->canManage()" />
                @else
                    <flux:input wire:model="percent_fee" :label="__('Percent')" placeholder="1.5" inputmode="decimal" :disabled="! $this->canManage()" />
                @endif
            </div>
            <flux:text size="sm">{{ __('Each overdue invoice is charged once. Invoices that fell due before late fees were switched on are not charged.') }}</flux:text>
        @endif

        @if ($this->canManage())
            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">{{ __('Save settings') }}</flux:button>
            </div>
        @endif
    </form>

    <flux:modal name="recurring-charge-form" class="w-full max-w-lg">
        <form wire:submit="saveCharge" class="space-y-5">
            <flux:heading size="lg">{{ $editingChargeId ? __('Edit recurring charge') : __('Add recurring charge') }}</flux:heading>

            <flux:select wire:model.live="charge_type_id" :label="__('Charge type')">
                <flux:select.option value="">{{ __('Choose a charge type') }}</flux:select.option>
                @foreach ($this->chargeTypes as $chargeType)
                    <flux:select.option :value="$chargeType->id">{{ $chargeType->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="description" :label="__('Description on invoices')" required />

            <flux:select wire:model.live="applies_to_unit_id" :label="__('Billed to')">
                <flux:select.option value="">{{ __('Every unit') }}</flux:select.option>
                @foreach ($this->units as $unit)
                    <flux:select.option :value="$unit->id">{{ __('Unit :unit only', ['unit' => $unit->label()]) }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($applies_to_unit_id === '')
                <flux:radio.group wire:model.live="method" :label="__('Amount')">
                    @foreach (App\Enums\RecurringChargeMethod::cases() as $option)
                        <flux:radio :value="$option->value" :label="$option->label()" />
                    @endforeach
                </flux:radio.group>
            @endif

            <flux:input wire:model="amount" inputmode="decimal" placeholder="0.00" required
                :label="$applies_to_unit_id === '' && $method === 'unit_factor' ? __('Monthly total for the community') : __('Monthly amount per unit')" />

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="starts_on" type="date" :label="__('Starts')" required />
                <flux:input wire:model="ends_on" type="date" :label="__('Ends (optional)')" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>

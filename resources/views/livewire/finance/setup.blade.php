<section class="w-full space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Accounts & charges') }}</flux:heading>
        <flux:subheading>{{ $community->name }}</flux:subheading>
    </div>

    @include('livewire.finance.partials.nav')

    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">{{ __('Charge types') }}</flux:heading>
            @if ($this->canManage())
                <flux:button size="sm" icon="plus" wire:click="createChargeType">{{ __('Add charge type') }}</flux:button>
            @endif
        </div>

        @if ($this->chargeTypes->isEmpty())
            <div class="rounded-xl border border-dashed border-zinc-300 p-6 text-center dark:border-zinc-600">
                <flux:text>{{ __('No charge types yet. Add one (e.g. "Monthly fees") to start invoicing.') }}</flux:text>
            </div>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Income account') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Default amount') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->chargeTypes as $chargeType)
                        <flux:table.row :key="$chargeType->id">
                            <flux:table.cell variant="strong">
                                {{ $chargeType->name }}
                                @unless ($chargeType->is_active)
                                    <flux:badge size="sm">{{ __('Inactive') }}</flux:badge>
                                @endunless
                            </flux:table.cell>
                            <flux:table.cell>{{ $chargeType->account->label() }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $chargeType->default_amount_cents === null ? '—' : App\Support\Finance\Money::of($chargeType->default_amount_cents)->format() }}</flux:table.cell>
                            <flux:table.cell align="end">
                                @can('update', $chargeType)
                                    <flux:button size="sm" variant="ghost" wire:click="editChargeType({{ $chargeType->id }})">{{ __('Edit') }}</flux:button>
                                    <flux:button size="sm" variant="ghost" wire:click="toggleChargeType({{ $chargeType->id }})">{{ $chargeType->is_active ? __('Deactivate') : __('Activate') }}</flux:button>
                                @endcan
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </div>

    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">{{ __('Chart of accounts') }}</flux:heading>
            @if ($this->canManage())
                <flux:button size="sm" icon="plus" wire:click="createAccount">{{ __('Add account') }}</flux:button>
            @endif
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Code') }}</flux:table.column>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Balance') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->accounts as $account)
                    <flux:table.row :key="$account->id">
                        <flux:table.cell>{{ $account->code }}</flux:table.cell>
                        <flux:table.cell variant="strong">
                            {{ $account->name }}
                            @unless ($account->is_active)
                                <flux:badge size="sm">{{ __('Inactive') }}</flux:badge>
                            @endunless
                        </flux:table.cell>
                        <flux:table.cell>{{ $account->type->label() }}</flux:table.cell>
                        <flux:table.cell align="end">{{ ($this->balances->get($account->id) ?? App\Support\Finance\Money::zero())->format() }}</flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($this->canManage() && ! $account->isSystem())
                                <flux:button size="sm" variant="ghost" wire:click="toggleAccount({{ $account->id }})">{{ $account->is_active ? __('Deactivate') : __('Activate') }}</flux:button>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="account-form" class="w-full max-w-md">
        <form wire:submit="saveAccount" class="space-y-5">
            <flux:heading size="lg">{{ __('Add account') }}</flux:heading>
            <div class="grid grid-cols-3 gap-4">
                <flux:input wire:model="code" :label="__('Code')" required />
                <div class="col-span-2">
                    <flux:input wire:model="name" :label="__('Name')" required />
                </div>
            </div>
            <flux:select wire:model="type" :label="__('Type')">
                @foreach (App\Enums\AccountType::cases() as $option)
                    <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Add account') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="charge-type-form" class="w-full max-w-md">
        <form wire:submit="saveChargeType" class="space-y-5">
            <flux:heading size="lg">{{ $editingChargeTypeId ? __('Edit charge type') : __('Add charge type') }}</flux:heading>
            <flux:input wire:model="charge_name" :label="__('Name')" required />
            <flux:select wire:model="account_id" :label="__('Income account')">
                <flux:select.option value="">{{ __('Choose an account') }}</flux:select.option>
                @foreach ($this->accounts->where('type', App\Enums\AccountType::Income)->where('is_active', true) as $option)
                    <flux:select.option :value="$option->id">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="default_amount" :label="__('Default amount (optional)')" placeholder="0.00" inputmode="decimal" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>

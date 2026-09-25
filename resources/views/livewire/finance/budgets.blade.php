<section class="w-full space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Budget') }}</flux:heading>
        <flux:subheading>{{ $community->name }}</flux:subheading>
    </div>

    @include('livewire.finance.partials.nav')

    <div class="flex flex-wrap items-end justify-between gap-3">
        <flux:radio.group wire:model.live="year" variant="segmented" :label="__('Fiscal year')">
            <flux:radio value="current" :label="__('This year')" />
            <flux:radio value="next" :label="__('Next year')" />
        </flux:radio.group>
        <flux:text>{{ __('Fiscal :year', ['year' => $this->fiscalYear->label()]) }} · {{ $this->fiscalYear->starts_on->toFormattedDateString() }} &ndash; {{ $this->fiscalYear->ends_on->toFormattedDateString() }}</flux:text>
    </div>

    <form wire:submit="save" class="space-y-4">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Account') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Annual budget') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Per month') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ([App\Enums\AccountType::Income, App\Enums\AccountType::Expense] as $type)
                    <flux:table.row class="bg-zinc-50 dark:bg-zinc-800/60">
                        <flux:table.cell variant="strong" colspan="3">{{ $type === App\Enums\AccountType::Income ? __('Income') : __('Expenses') }}</flux:table.cell>
                    </flux:table.row>
                    @foreach ($this->accounts->where('type', $type) as $account)
                        <flux:table.row :key="$account->id">
                            <flux:table.cell class="ps-6">
                                {{ $account->label() }}
                                @unless ($account->is_active)
                                    <flux:badge size="sm">{{ __('Inactive') }}</flux:badge>
                                @endunless
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:input wire:model.blur="amounts.{{ $account->id }}" size="sm" class="max-w-40 ms-auto text-end" placeholder="0.00" inputmode="decimal" :disabled="! $this->canManage()" :aria-label="__('Annual budget for :account', ['account' => $account->name])" />
                                <flux:error name="amounts.{{ $account->id }}" />
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                {{ $this->monthlyFor($account->id)?->format() }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                @endforeach
                <flux:table.row>
                    <flux:table.cell variant="strong">{{ __('Total income') }}</flux:table.cell>
                    <flux:table.cell align="end" variant="strong">{{ $this->totals['income']->format() }}</flux:table.cell>
                    <flux:table.cell></flux:table.cell>
                </flux:table.row>
                <flux:table.row>
                    <flux:table.cell variant="strong">{{ __('Total expenses') }}</flux:table.cell>
                    <flux:table.cell align="end" variant="strong">{{ $this->totals['expenses']->format() }}</flux:table.cell>
                    <flux:table.cell></flux:table.cell>
                </flux:table.row>
                <flux:table.row>
                    <flux:table.cell variant="strong">{{ __('Surplus (deficit)') }}</flux:table.cell>
                    <flux:table.cell align="end" variant="strong" data-test="budget-net">{{ $this->totals['net']->format() }}</flux:table.cell>
                    <flux:table.cell></flux:table.cell>
                </flux:table.row>
            </flux:table.rows>
        </flux:table>

        @if ($this->canManage())
            <div class="flex justify-end gap-2">
                <flux:button :href="route('communities.finance.reports', [$community, 'report' => 'budget-vs-actual'])" wire:navigate>{{ __('Budget vs actual') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Save budget') }}</flux:button>
            </div>
        @endif
    </form>
</section>

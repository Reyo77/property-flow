<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Bank reconciliation') }}</flux:heading>
            <flux:subheading>{{ $community->name }}</flux:subheading>
        </div>

        @if ($this->canImport())
            <flux:button variant="primary" icon="arrow-up-tray" wire:click="create">{{ __('Import statement') }}</flux:button>
        @endif
    </div>

    @include('livewire.finance.partials.nav')

    @if ($this->statements->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <flux:heading>{{ __('No statements imported yet') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Import your bank\'s CSV export each month to check the books against the bank.') }}</flux:text>
        </div>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Period') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Closing balance') }}</flux:table.column>
                <flux:table.column>{{ __('Lines') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->statements as $statement)
                    <flux:table.row :key="$statement->id">
                        <flux:table.cell variant="strong">{{ $statement->starts_on->toFormattedDateString() }} &ndash; {{ $statement->ends_on->toFormattedDateString() }}</flux:table.cell>
                        <flux:table.cell align="end">{{ App\Support\Finance\Money::of($statement->closing_balance_cents)->format() }}</flux:table.cell>
                        <flux:table.cell>{{ __(':matched of :total matched', ['matched' => $statement->lines_count - $statement->unmatched_count, 'total' => $statement->lines_count]) }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($statement->isReconciled())
                                <flux:badge size="sm" color="green">{{ __('Reconciled') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="amber">{{ __('In progress') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button size="sm" variant="ghost" :href="route('communities.finance.reconciliation.show', [$community, $statement])" wire:navigate>{{ __('Open') }}</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="statement-import" class="w-full max-w-lg">
        <form wire:submit="import" class="space-y-5">
            <flux:heading size="lg">{{ __('Import bank statement') }}</flux:heading>
            <flux:text size="sm">{{ __('A CSV with a date, a description, and either an amount column (deposits positive) or separate deposit and withdrawal columns.') }}</flux:text>

            <flux:input type="file" wire:model="file" :label="__('Statement file')" accept=".csv,.txt,.xlsx" />

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="starts_on" type="date" :label="__('From')" required />
                <flux:input wire:model="ends_on" type="date" :label="__('To')" required />
            </div>
            <flux:input wire:model="closing_balance" :label="__('Closing balance on the statement')" placeholder="0.00" inputmode="decimal" required />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Import') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>

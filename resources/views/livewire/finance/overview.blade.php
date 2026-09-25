<section class="w-full space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Finance') }}</flux:heading>
        <flux:subheading>{{ $community->name }}</flux:subheading>
    </div>

    @include('livewire.finance.partials.nav')

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:text>{{ __('Cash in bank') }}</flux:text>
            <flux:heading size="xl" data-test="cash">{{ $this->cash->format() }}</flux:heading>
        </div>
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:text>{{ __('Receivables') }}</flux:text>
            <flux:heading size="xl" data-test="receivables">{{ $this->receivables->format() }}</flux:heading>
        </div>
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:text>{{ __('Overdue') }}</flux:text>
            <flux:heading size="xl">{{ $this->overdue['amount']->format() }}</flux:heading>
            <flux:text size="sm">{{ trans_choice(':count invoice|:count invoices', $this->overdue['count']) }}</flux:text>
        </div>
    </div>

    <div class="space-y-3">
        <flux:heading size="lg">{{ __('Unit balances') }}</flux:heading>

        @if ($this->unitBalances->isEmpty())
            <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
                <flux:heading>{{ __('Every unit is paid up') }}</flux:heading>
            </div>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Unit') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Balance') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->unitBalances as $row)
                        <flux:table.row :key="$row['unit']->id">
                            <flux:table.cell>
                                <flux:link :href="route('communities.units.account', [$community, $row['unit']])" wire:navigate>{{ $row['unit']->label() }}</flux:link>
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                @if ($row['balance']->isNegative())
                                    <flux:badge size="sm" color="green">{{ __(':amount credit', ['amount' => $row['balance']->negate()->format()]) }}</flux:badge>
                                @else
                                    {{ $row['balance']->format() }}
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </div>
</section>

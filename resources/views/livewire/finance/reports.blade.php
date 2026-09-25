<section class="w-full space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Financial reports') }}</flux:heading>
        <flux:subheading>{{ $community->name }}</flux:subheading>
    </div>

    @include('livewire.finance.partials.nav')

    <div class="flex flex-wrap items-end gap-3">
        <flux:select wire:model.live="report" :label="__('Report')" class="max-w-56">
            @foreach (App\Enums\FinancialReport::cases() as $option)
                <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        @if ($this->reportType->isForPeriod())
            <flux:input wire:model.live="from" type="date" :label="__('From')" />
        @endif
        <flux:input wire:model.live="to" type="date" :label="$this->reportType->isForPeriod() ? __('To') : __('As of')" />
        @if ($this->reportType === App\Enums\FinancialReport::GeneralLedger)
            <flux:select wire:model.live="account" :label="__('Account')" class="max-w-64">
                <flux:select.option value="">{{ __('All accounts') }}</flux:select.option>
                @foreach ($this->accounts as $option)
                    <flux:select.option :value="$option->id">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif
        <flux:spacer />
        <flux:button size="sm" icon="arrow-down-tray" :href="route('communities.finance.reports.export', [$community, ...$this->exportQuery('csv')])">{{ __('CSV') }}</flux:button>
        <flux:button size="sm" icon="arrow-down-tray" :href="route('communities.finance.reports.export', [$community, ...$this->exportQuery('xlsx')])">{{ __('Excel') }}</flux:button>
    </div>

    @if ($this->table === null)
        <flux:callout variant="warning" icon="exclamation-triangle" :heading="__('Choose a valid date range.')" />
    @else
        <div>
            <flux:heading size="lg">{{ $this->table->title }}</flux:heading>
            <flux:text>{{ $this->table->period }}</flux:text>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ $this->table->labelHeading }}</flux:table.column>
                @foreach ($this->table->columns as $column)
                    <flux:table.column align="end">{{ $column }}</flux:table.column>
                @endforeach
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->table->rows() as $row)
                    <flux:table.row @class(['bg-zinc-50 dark:bg-zinc-800/60' => $row['style'] === 'section'])>
                        @if ($row['style'] === 'section')
                            <flux:table.cell variant="strong" colspan="{{ count($this->table->columns) + 1 }}">{{ $row['label'] }}</flux:table.cell>
                        @else
                            <flux:table.cell :variant="$row['style'] === 'line' ? null : 'strong'" @class(['ps-6' => $row['style'] === 'line', 'text-base' => $row['style'] === 'grand'])>
                                @if (isset($row['meta']['unit_id']))
                                    <flux:link :href="route('communities.units.account', [$community, $row['meta']['unit_id']])" wire:navigate>{{ $row['label'] }}</flux:link>
                                @else
                                    {{ $row['label'] }}
                                @endif
                            </flux:table.cell>
                            @foreach ($this->table->columns as $index => $column)
                                <flux:table.cell align="end" :variant="$row['style'] === 'line' ? null : 'strong'" @class(['text-red-600 dark:text-red-400' => ($row['amounts'][$index] ?? null)?->isNegative()])>
                                    {{ ($row['amounts'][$index] ?? null)?->format() }}
                                </flux:table.cell>
                            @endforeach
                        @endif
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="{{ count($this->table->columns) + 1 }}">{{ __('Nothing posted in this period.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    @endif
</section>

<section class="w-full space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <flux:heading size="xl" level="1">{{ $community->name }}</flux:heading>
                <flux:badge size="sm">{{ $community->type->label() }}</flux:badge>
            </div>
            <flux:subheading>
                {{ collect([$community->address_line_1, $community->city, $community->region, $community->country])->filter()->implode(', ') }}
            </flux:subheading>
        </div>

        @can('update', $community)
            <flux:button icon="pencil-square" :href="route('communities.edit', $community)" wire:navigate>{{ __('Edit') }}</flux:button>
        @endcan
    </div>

    @if ($this->unitFactorIsUnbalanced)
        <flux:callout variant="warning" icon="exclamation-triangle" data-test="unit-factor-warning">
            <flux:callout.heading>{{ __('Unit factors add up to :total%', ['total' => rtrim(rtrim($this->totalUnitFactor, '0'), '.')]) }}</flux:callout.heading>
            <flux:callout.text>{{ __('Unit factors should total exactly 100% so shared costs and votes are split correctly.') }}</flux:callout.text>
        </flux:callout>
    @endif

    <div class="grid gap-4 sm:grid-cols-3">
        <a href="{{ route('communities.buildings.index', $community) }}" wire:navigate class="rounded-xl border border-zinc-200 p-5 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800/50">
            <flux:text>{{ __('Buildings') }}</flux:text>
            <flux:heading size="xl">{{ number_format($this->buildingsCount) }}</flux:heading>
        </a>
        <a href="{{ route('communities.units.index', $community) }}" wire:navigate class="rounded-xl border border-zinc-200 p-5 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800/50">
            <flux:text>{{ __('Units') }}</flux:text>
            <flux:heading size="xl">{{ number_format($this->unitsCount) }}</flux:heading>
        </a>
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:text>{{ __('Total unit factor') }}</flux:text>
            <flux:heading size="xl">
                {{ $this->totalUnitFactor === null ? '—' : rtrim(rtrim($this->totalUnitFactor, '0'), '.').'%' }}
            </flux:heading>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div>
            <flux:text>{{ __('Timezone') }}</flux:text>
            <flux:heading>{{ $community->timezone }}</flux:heading>
        </div>
        <div>
            <flux:text>{{ __('Currency') }}</flux:text>
            <flux:heading>{{ $community->currency }}</flux:heading>
        </div>
        <div>
            <flux:text>{{ __('Area unit') }}</flux:text>
            <flux:heading>{{ $community->area_unit->label() }}</flux:heading>
        </div>
    </div>
</section>

<section class="w-full space-y-8">
    <div>
        <flux:heading size="xl" level="1">{{ __('Welcome, :name', ['name' => auth()->user()->name]) }}</flux:heading>
        <flux:subheading>{{ auth()->user()->company?->name }}</flux:subheading>
    </div>

    @if ($this->totals !== null)
        @if ($this->totals['communities'] === 0)
            <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600" data-test="onboarding">
                <flux:icon name="building-office-2" class="mx-auto mb-3 size-8 text-zinc-400" />
                @can('create', App\Models\Community::class)
                    <flux:heading>{{ __('Set up your first community') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('Add a community, then its buildings and units.') }}</flux:text>
                    <flux:button variant="primary" icon="plus" class="mt-4" :href="route('communities.create')" wire:navigate>{{ __('New community') }}</flux:button>
                @else
                    <flux:heading>{{ __('No communities assigned yet') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('Ask your company admin to give you access to a community.') }}</flux:text>
                @endcan
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" data-test="portfolio-totals">
                <a href="{{ route('communities.index') }}" wire:navigate class="rounded-xl border border-zinc-200 p-5 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800/50">
                    <flux:text>{{ __('Communities') }}</flux:text>
                    <flux:heading size="xl">{{ number_format($this->totals['communities']) }}</flux:heading>
                </a>
                <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                    <flux:text>{{ __('Units') }}</flux:text>
                    <flux:heading size="xl">{{ number_format($this->totals['units']) }}</flux:heading>
                </div>
                <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                    <flux:text>{{ __('Occupancy') }}</flux:text>
                    <flux:heading size="xl">
                        {{ $this->totals['units'] === 0 ? '—' : round($this->totals['occupied_units'] / $this->totals['units'] * 100).'%' }}
                    </flux:heading>
                    <flux:text class="text-xs">{{ __(':occupied of :units units', ['occupied' => number_format($this->totals['occupied_units']), 'units' => number_format($this->totals['units'])]) }}</flux:text>
                </div>
                <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                    <flux:text>{{ __('Residents') }}</flux:text>
                    <flux:heading size="xl">{{ number_format($this->totals['residents']) }}</flux:heading>
                </div>
            </div>
        @endif
    @endif

    @if ($this->myHomes->isNotEmpty())
        <div class="space-y-3" data-test="my-homes">
            <flux:heading size="lg">{{ __('My home') }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($this->myHomes as $residency)
                    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700" wire:key="home-{{ $residency->id }}">
                        <flux:text>{{ $residency->community->name }}</flux:text>
                        <flux:heading size="lg">
                            {{ $residency->unit->building ? $residency->unit->building->name.' · ' : '' }}{{ __('Unit :number', ['number' => $residency->unit->number]) }}
                        </flux:heading>
                        <flux:text class="mt-1">
                            {{ $residency->type->label() }}
                            @if ($residency->moved_in_on)
                                · {{ __('since :date', ['date' => $residency->moved_in_on->toFormattedDateString()]) }}
                            @endif
                        </flux:text>
                        @php($balance = $this->homeBalances[$residency->unit_id])
                        <div class="mt-4 flex items-end justify-between gap-3 border-t border-zinc-200 pt-3 dark:border-zinc-700" data-test="home-balance">
                            <div>
                                <flux:text size="sm">{{ $balance->isNegative() ? __('Credit on account') : __('Balance owing') }}</flux:text>
                                <flux:heading size="lg">{{ $balance->isNegative() ? $balance->negate()->format() : $balance->format() }}</flux:heading>
                            </div>
                            <flux:link :href="route('communities.units.account', [$residency->community, $residency->unit])" wire:navigate>{{ __('View account') }}</flux:link>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @elseif ($this->totals === null)
        <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600" data-test="no-access">
            <flux:heading>{{ __('Nothing to show yet') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Your account is not linked to a unit or a team role. Contact your property manager.') }}</flux:text>
        </div>
    @endif
</section>

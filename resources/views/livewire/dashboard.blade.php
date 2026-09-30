<section class="w-full space-y-8">
    <div class="relative overflow-hidden rounded-2xl border border-zinc-200 bg-gradient-to-br from-zinc-50 via-white to-white p-6 dark:border-zinc-700 dark:from-zinc-800/60 dark:via-zinc-900 dark:to-zinc-900 sm:p-8">
        <div class="absolute -end-16 -top-16 size-56 rounded-full bg-emerald-500/5 blur-2xl"></div>
        <div class="relative flex flex-wrap items-start justify-between gap-4">
            <div>
                <flux:text class="text-xs font-semibold tracking-wide text-emerald-600 uppercase dark:text-emerald-400">{{ __('Portfolio overview') }}</flux:text>
                <flux:heading size="xl" level="1" class="mt-1">{{ __('Welcome, :name', ['name' => auth()->user()->name]) }}</flux:heading>
                <flux:subheading>{{ auth()->user()->company?->name }}</flux:subheading>
            </div>
            <flux:button variant="ghost" icon="book-open" :href="route('guide')" wire:navigate>{{ __('Guide') }}</flux:button>
        </div>
    </div>

    @if ($this->totals !== null)
        @if ($this->totals['communities'] === 0)
            <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600" data-test="onboarding">
                <div class="mx-auto mb-3 flex size-14 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                    <flux:icon name="building-office-2" class="size-7" />
                </div>
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
                <a href="{{ route('communities.index') }}" wire:navigate class="group rounded-xl border border-zinc-200 p-5 transition hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-sm dark:border-zinc-700 dark:hover:border-blue-500/40">
                    <div class="flex items-center gap-3">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-blue-500/10 text-blue-600 dark:text-blue-400">
                            <flux:icon name="squares-2x2" class="size-5" />
                        </div>
                        <flux:text>{{ __('Communities') }}</flux:text>
                    </div>
                    <flux:heading size="xl" class="mt-3">{{ number_format($this->totals['communities']) }}</flux:heading>
                </a>
                <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                    <div class="flex items-center gap-3">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-zinc-500/10 text-zinc-600 dark:text-zinc-400">
                            <flux:icon name="home-modern" class="size-5" />
                        </div>
                        <flux:text>{{ __('Units') }}</flux:text>
                    </div>
                    <flux:heading size="xl" class="mt-3">{{ number_format($this->totals['units']) }}</flux:heading>
                </div>
                <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                    <div class="flex items-center gap-3">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                            <flux:icon name="chart-pie" class="size-5" />
                        </div>
                        <flux:text>{{ __('Occupancy') }}</flux:text>
                    </div>
                    <flux:heading size="xl" class="mt-3">
                        {{ $this->totals['units'] === 0 ? '—' : round($this->totals['occupied_units'] / $this->totals['units'] * 100).'%' }}
                    </flux:heading>
                    <flux:text class="text-xs">{{ __(':occupied of :units units', ['occupied' => number_format($this->totals['occupied_units']), 'units' => number_format($this->totals['units'])]) }}</flux:text>
                </div>
                <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                    <div class="flex items-center gap-3">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400">
                            <flux:icon name="users" class="size-5" />
                        </div>
                        <flux:text>{{ __('Residents') }}</flux:text>
                    </div>
                    <flux:heading size="xl" class="mt-3">{{ number_format($this->totals['residents']) }}</flux:heading>
                </div>
            </div>

            @if ($this->portfolioTrend !== null)
                <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700 space-y-3" data-test="portfolio-trend">
                    <div class="flex items-center gap-3">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                            <flux:icon name="presentation-chart-line" class="size-5" />
                        </div>
                        <flux:heading size="lg">{{ __('Occupancy') }}</flux:heading>
                    </div>
                    <x-charts.trend-line
                        :series="[[
                            'label' => __('Occupancy'),
                            'color' => 'emerald-500',
                            'values' => array_column($this->portfolioTrend, 'occupancy_percent'),
                            'display' => array_map(fn (array $point) => $point['occupancy_percent'].'%', $this->portfolioTrend),
                        ]]"
                        :labels="array_map(fn (array $point) => $point['month']->translatedFormat('M'), $this->portfolioTrend)"
                    />
                </div>
            @endif
        @endif
    @endif

    @if ($this->myHomes->isNotEmpty())
        <div class="space-y-3" data-test="my-homes">
            <flux:heading size="lg">{{ __('My home') }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($this->myHomes as $residency)
                    <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700" wire:key="home-{{ $residency->id }}">
                        <div class="flex items-center gap-3 border-b border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-800/40">
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-blue-500/10 text-blue-600 dark:text-blue-400">
                                <flux:icon name="home-modern" class="size-5" />
                            </div>
                            <div>
                                <flux:text>{{ $residency->community->name }}</flux:text>
                                <flux:heading size="lg">
                                    {{ $residency->unit->building ? $residency->unit->building->name.' · ' : '' }}{{ __('Unit :number', ['number' => $residency->unit->number]) }}
                                </flux:heading>
                            </div>
                        </div>
                        <div class="p-5">
                            <flux:text>
                                {{ $residency->type->label() }}
                                @if ($residency->moved_in_on)
                                    · {{ __('since :date', ['date' => $residency->moved_in_on->toFormattedDateString()]) }}
                                @endif
                            </flux:text>
                            @php($balance = $this->homeBalances[$residency->unit_id])
                            <div class="mt-4 flex items-end justify-between gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" data-test="home-balance">
                                <div>
                                    <flux:badge size="sm" :color="$balance->isNegative() ? 'green' : ($balance->isZero() ? 'zinc' : 'amber')">
                                        {{ $balance->isNegative() ? __('Credit on account') : __('Balance owing') }}
                                    </flux:badge>
                                    <flux:heading size="lg" class="mt-1">{{ $balance->isNegative() ? $balance->negate()->format() : $balance->format() }}</flux:heading>
                                </div>
                                <flux:link :href="route('communities.units.account', [$residency->community, $residency->unit])" wire:navigate>{{ __('View account') }}</flux:link>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2" data-test="home-links">
                                <flux:button size="sm" icon="calendar-date-range" :href="route('communities.amenities.index', $residency->community)" wire:navigate>{{ __('Amenities') }}</flux:button>
                                <flux:button size="sm" icon="check-badge" :href="route('communities.ballots.index', $residency->community)" wire:navigate>{{ __('Ballots') }}</flux:button>
                                <flux:button size="sm" icon="users" :href="route('communities.meetings.index', $residency->community)" wire:navigate>{{ __('Meetings') }}</flux:button>
                                <flux:button size="sm" icon="chart-bar" :href="route('communities.surveys.index', $residency->community)" wire:navigate>{{ __('Surveys') }}</flux:button>
                                <flux:button size="sm" icon="pencil-square" :href="route('communities.consent-forms.index', $residency->community)" wire:navigate>{{ __('Forms') }}</flux:button>
                                <flux:button size="sm" icon="chat-bubble-left-right" :href="route('communities.forum.index', $residency->community)" wire:navigate>{{ __('Community board') }}</flux:button>
                                @if ($residency->type === App\Enums\ResidencyType::Owner)
                                    <flux:button size="sm" icon="home-modern" :href="route('communities.architectural-requests.index', $residency->community)" wire:navigate>{{ __('Renovations') }}</flux:button>
                                    <flux:button size="sm" icon="shield-exclamation" :href="route('communities.violations.index', $residency->community)" wire:navigate>{{ __('Bylaw notices') }}</flux:button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @elseif ($this->vendorOpenWorkOrdersCount !== null)
        <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600" data-test="vendor-summary">
            <div class="mx-auto mb-3 flex size-14 items-center justify-center rounded-full bg-blue-500/10 text-blue-600 dark:text-blue-400">
                <flux:icon name="clipboard-document-list" class="size-7" />
            </div>
            <flux:heading>
                {{ trans_choice(':count open work order|:count open work orders', $this->vendorOpenWorkOrdersCount, ['count' => $this->vendorOpenWorkOrdersCount]) }}
            </flux:heading>
            <flux:text class="mt-1">{{ __('Jobs assigned to you, across every community.') }}</flux:text>
            <flux:button variant="primary" class="mt-4" :href="route('work-orders.mine')" wire:navigate>{{ __('My work orders') }}</flux:button>
        </div>
    @elseif ($this->totals === null)
        <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600" data-test="no-access">
            <div class="mx-auto mb-3 flex size-14 items-center justify-center rounded-full bg-zinc-500/10 text-zinc-500 dark:text-zinc-400">
                <flux:icon name="information-circle" class="size-7" />
            </div>
            <flux:heading>{{ __('Nothing to show yet') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Your account is not linked to a unit or a team role. Contact your property manager.') }}</flux:text>
        </div>
    @endif
</section>

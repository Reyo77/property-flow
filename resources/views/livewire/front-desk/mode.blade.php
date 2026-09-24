<section class="w-full space-y-8">
    <div>
        <flux:heading size="xl" level="1">{{ __('Front desk') }}</flux:heading>
        <flux:subheading>{{ $community->name }}</flux:subheading>
    </div>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
        @can('viewAny', [App\Models\Package::class, $community])
            <flux:button :href="route('communities.packages.index', $community)" wire:navigate size="base" class="h-24! text-lg!" icon="archive-box">{{ __('Packages') }}</flux:button>
        @endcan
        @can('viewAny', [App\Models\Visitor::class, $community])
            <flux:button :href="route('communities.visitors.index', $community)" wire:navigate size="base" class="h-24! text-lg!" icon="user-plus">{{ __('Visitors') }}</flux:button>
        @endcan
        @can('viewAny', [App\Models\ParkingPermit::class, $community])
            <flux:button :href="route('communities.parking-permits.index', $community)" wire:navigate size="base" class="h-24! text-lg!" icon="truck">{{ __('Parking') }}</flux:button>
        @endcan
        @can('viewAny', [App\Models\IncidentReport::class, $community])
            <flux:button :href="route('communities.incident-reports.index', $community)" wire:navigate size="base" class="h-24! text-lg!" icon="exclamation-triangle">{{ __('Incident') }}</flux:button>
        @endcan
        @can('viewAny', [App\Models\AccessKey::class, $community])
            <flux:button :href="route('communities.access-keys.index', $community)" wire:navigate size="base" class="h-24! text-lg!" icon="key">{{ __('Keys') }}</flux:button>
        @endcan
        @can('viewAny', [App\Models\PatrolRoute::class, $community])
            <flux:button :href="route('communities.patrol-routes.index', $community)" wire:navigate size="base" class="h-24! text-lg!" icon="map">{{ __('Patrols') }}</flux:button>
        @endcan
        @can('viewAny', [App\Models\ShiftLogEntry::class, $community])
            <flux:button :href="route('communities.shift-log.index', $community)" wire:navigate size="base" class="h-24! text-lg!" icon="clipboard-document-list">{{ __('Shift log') }}</flux:button>
        @endcan
    </div>

    <div class="space-y-3">
        <div class="flex items-center gap-2">
            <flux:heading size="lg">{{ __('Live activity') }}</flux:heading>
            <span class="size-2 rounded-full bg-green-500" title="{{ __('Live') }}"></span>
        </div>

        @if (empty($activity))
            <flux:text>{{ __('Activity logged elsewhere in the app will appear here as it happens.') }}</flux:text>
        @else
            <div class="space-y-2">
                @foreach ($activity as $event)
                    <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" wire:key="activity-{{ $loop->index }}-{{ $event['occurred_at'] }}">
                        <flux:text>{{ $event['message'] }}</flux:text>
                        <flux:text class="text-xs">{{ \Illuminate\Support\Carbon::parse($event['occurred_at'])->diffForHumans() }}</flux:text>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>

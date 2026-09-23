<section class="w-full space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Welcome, :name', ['name' => auth()->user()->name]) }}</flux:heading>
        <flux:subheading>{{ auth()->user()->company?->name }}</flux:subheading>
    </div>

    @if ($this->totals !== null)
        @if ($this->totals['communities'] === 0)
            <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600" data-test="onboarding">
                <flux:icon name="building-office-2" class="mx-auto mb-3 size-8 text-zinc-400" />
                <flux:heading>{{ __('Set up your first community') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Add a community, then its buildings and units.') }}</flux:text>

                @can('create', App\Models\Community::class)
                    <flux:button variant="primary" icon="plus" class="mt-4" :href="route('communities.create')" wire:navigate>{{ __('New community') }}</flux:button>
                @endcan
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-3">
                <a href="{{ route('communities.index') }}" wire:navigate class="rounded-xl border border-zinc-200 p-5 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800/50">
                    <flux:text>{{ __('Communities') }}</flux:text>
                    <flux:heading size="xl">{{ number_format($this->totals['communities']) }}</flux:heading>
                </a>
                <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                    <flux:text>{{ __('Buildings') }}</flux:text>
                    <flux:heading size="xl">{{ number_format($this->totals['buildings']) }}</flux:heading>
                </div>
                <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                    <flux:text>{{ __('Units') }}</flux:text>
                    <flux:heading size="xl">{{ number_format($this->totals['units']) }}</flux:heading>
                </div>
            </div>
        @endif
    @endif
</section>

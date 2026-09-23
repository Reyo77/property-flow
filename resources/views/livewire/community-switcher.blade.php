<div>
    <flux:dropdown position="bottom" align="start">
        <flux:button variant="subtle" icon="building-office-2" icon:trailing="chevrons-up-down" class="w-full justify-between" data-test="community-switcher">
            <span class="truncate">{{ $this->current?->name ?? __('No community selected') }}</span>
        </flux:button>

        <flux:menu class="min-w-60">
            @forelse ($this->communities as $community)
                <flux:menu.item wire:key="community-{{ $community->id }}" wire:click="switchTo({{ $community->id }})" :icon="$community->id === $this->current?->id ? 'check' : null">
                    {{ $community->name }}
                </flux:menu.item>
            @empty
                <flux:menu.item disabled>{{ __('No communities yet') }}</flux:menu.item>
            @endforelse

            @can('viewAny', App\Models\Community::class)
                <flux:menu.separator />
                <flux:menu.item icon="squares-2x2" :href="route('communities.index')" wire:navigate>{{ __('All communities') }}</flux:menu.item>
            @endcan
        </flux:menu>
    </flux:dropdown>
</div>

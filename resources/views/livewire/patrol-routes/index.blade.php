<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Patrols') }}</flux:heading>
            <flux:subheading>{{ $community->name }}</flux:subheading>
        </div>

        @can('create', [App\Models\PatrolRoute::class, $community])
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('New route') }}</flux:button>
        @endcan
    </div>

    @if ($this->routes->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <flux:heading>{{ __('No patrol routes yet') }}</flux:heading>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->routes as $route)
                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" wire:key="route-{{ $route->id }}">
                    <flux:heading>
                        <flux:link :href="route('communities.patrol-routes.show', [$community, $route])" wire:navigate>{{ $route->name }}</flux:link>
                    </flux:heading>
                    <flux:text class="text-sm">{{ trans_choice(':count checkpoint|:count checkpoints', $route->checkpoints_count, ['count' => $route->checkpoints_count]) }}</flux:text>
                </div>
            @endforeach
        </div>
    @endif

    <flux:modal name="patrol-route-form" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ __('New patrol route') }}</flux:heading>

            <flux:input wire:model="name" :label="__('Name')" required />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>

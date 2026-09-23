<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Communities') }}</flux:heading>
            <flux:subheading>{{ __('Every property your company manages.') }}</flux:subheading>
        </div>

        @can('create', App\Models\Community::class)
            <flux:button variant="primary" icon="plus" :href="route('communities.create')" wire:navigate>
                {{ __('New community') }}
            </flux:button>
        @endcan
    </div>

    @if ($this->communities->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <flux:icon name="building-office-2" class="mx-auto mb-3 size-8 text-zinc-400" />
            <flux:heading>{{ __('No communities yet') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Add your first condo, HOA or rental community to get started.') }}</flux:text>
        </div>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column>{{ __('City') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Buildings') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Units') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->communities as $community)
                    <flux:table.row :key="$community->id">
                        <flux:table.cell variant="strong">
                            <flux:link :href="route('communities.show', $community)" wire:navigate>{{ $community->name }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell><flux:badge size="sm">{{ $community->type->label() }}</flux:badge></flux:table.cell>
                        <flux:table.cell>{{ $community->city }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $community->buildings_count }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $community->units_count }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</section>

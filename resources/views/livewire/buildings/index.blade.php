<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Buildings') }}</flux:heading>
            <flux:subheading>{{ $community->name }}</flux:subheading>
        </div>

        @can('create', [App\Models\Building::class, $community])
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add building') }}</flux:button>
        @endcan
    </div>

    @if ($this->buildings->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <flux:heading>{{ __('No buildings yet') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Communities with homes instead of buildings can skip this and add units directly.') }}</flux:text>
        </div>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Address') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Floors') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Units') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->buildings as $building)
                    <flux:table.row :key="$building->id">
                        <flux:table.cell variant="strong">{{ $building->name }}</flux:table.cell>
                        <flux:table.cell>{{ $building->address }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $building->floors }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $building->units_count }}</flux:table.cell>
                        <flux:table.cell align="end">
                            @can('update', $building)
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" :aria-label="__('Actions')" />
                                    <flux:menu>
                                        <flux:menu.item icon="pencil-square" wire:click="edit({{ $building->id }})">{{ __('Edit') }}</flux:menu.item>
                                        <flux:menu.item icon="trash" variant="danger" wire:click="delete({{ $building->id }})" wire:confirm="{{ __('Delete :name?', ['name' => $building->name]) }}">{{ __('Delete') }}</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            @endcan
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="building-form" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingBuildingId ? __('Edit building') : __('Add building') }}</flux:heading>

            <flux:input wire:model="name" :label="__('Name')" required />
            <flux:input wire:model="address" :label="__('Address')" />
            <flux:input wire:model="floors" :label="__('Floors')" type="number" min="1" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>

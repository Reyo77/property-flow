<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Units') }}</flux:heading>
            <flux:subheading>{{ $community->name }}</flux:subheading>
        </div>

        <div class="flex gap-2">
            @can('import', [App\Models\Unit::class, $community])
                <flux:button icon="arrow-up-tray" :href="route('communities.units.import', $community)" wire:navigate>{{ __('Import') }}</flux:button>
            @endcan
            @can('create', [App\Models\Unit::class, $community])
                <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add unit') }}</flux:button>
            @endcan
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search unit, parking or locker')" class="max-w-xs" />

        @if ($this->buildings->isNotEmpty())
            <flux:select wire:model.live="buildingFilter" class="max-w-xs">
                <flux:select.option value="">{{ __('All buildings') }}</flux:select.option>
                @foreach ($this->buildings as $building)
                    <flux:select.option :value="$building->id">{{ $building->name }}</flux:select.option>
                @endforeach
                <flux:select.option value="none">{{ __('No building') }}</flux:select.option>
            </flux:select>
        @endif
    </div>

    <flux:table :paginate="$this->units">
        <flux:table.columns>
            <flux:table.column>{{ __('Unit') }}</flux:table.column>
            <flux:table.column>{{ __('Building') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Floor') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Area (:unit)', ['unit' => $community->area_unit->label()]) }}</flux:table.column>
            <flux:table.column align="end">{{ __('Unit factor %') }}</flux:table.column>
            <flux:table.column>{{ __('Parking') }}</flux:table.column>
            <flux:table.column>{{ __('Locker') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->units as $unit)
                <flux:table.row :key="$unit->id">
                    <flux:table.cell variant="strong">{{ $unit->number }}</flux:table.cell>
                    <flux:table.cell>{{ $unit->building?->name }}</flux:table.cell>
                    <flux:table.cell align="end">{{ $unit->floor }}</flux:table.cell>
                    <flux:table.cell align="end">{{ $unit->area }}</flux:table.cell>
                    <flux:table.cell align="end">{{ $unit->unit_factor === null ? '' : rtrim(rtrim($unit->unit_factor, '0'), '.') }}</flux:table.cell>
                    <flux:table.cell>{{ $unit->parking }}</flux:table.cell>
                    <flux:table.cell>{{ $unit->locker }}</flux:table.cell>
                    <flux:table.cell align="end">
                        @can('update', $unit)
                            <flux:dropdown position="bottom" align="end">
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" :aria-label="__('Actions')" />
                                <flux:menu>
                                    <flux:menu.item icon="pencil-square" wire:click="edit({{ $unit->id }})">{{ __('Edit') }}</flux:menu.item>
                                    <flux:menu.item icon="trash" variant="danger" wire:click="delete({{ $unit->id }})" wire:confirm="{{ __('Delete unit :number?', ['number' => $unit->number]) }}">{{ __('Delete') }}</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        @endcan
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="8" class="text-center">
                        {{ $search === '' && $buildingFilter === '' ? __('No units yet. Add them one by one or import a spreadsheet.') : __('No units match your filters.') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="unit-form" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingUnitId ? __('Edit unit') : __('Add unit') }}</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="number" :label="__('Unit number')" required />

                <flux:select wire:model="building_id" :label="__('Building')">
                    <flux:select.option value="">{{ __('No building') }}</flux:select.option>
                    @foreach ($this->buildings as $building)
                        <flux:select.option :value="$building->id">{{ $building->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="floor" :label="__('Floor')" type="number" />
                <flux:input wire:model="area" :label="__('Area (:unit)', ['unit' => $community->area_unit->label()])" type="number" step="0.01" min="0" />
                <flux:input wire:model="unit_factor" :label="__('Unit factor %')" type="number" step="0.000001" min="0" max="100" />
                <flux:input wire:model="parking" :label="__('Parking')" />
                <flux:input wire:model="locker" :label="__('Locker')" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>

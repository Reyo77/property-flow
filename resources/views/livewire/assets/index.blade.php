<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Assets') }}</flux:heading>
            <flux:subheading>{{ $community->name }}</flux:subheading>
        </div>

        @can('create', [App\Models\Asset::class, $community])
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add asset') }}</flux:button>
        @endcan
    </div>

    @if ($this->assets->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <flux:heading>{{ __('No assets yet') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Track equipment like elevators and HVAC units, and set up recurring maintenance.') }}</flux:text>
        </div>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Category') }}</flux:table.column>
                <flux:table.column>{{ __('Location') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Schedules') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->assets as $asset)
                    <flux:table.row :key="$asset->id">
                        <flux:table.cell variant="strong">
                            <flux:link :href="route('communities.assets.show', [$community, $asset])" wire:navigate>{{ $asset->name }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $asset->category->label() }}</flux:table.cell>
                        <flux:table.cell>{{ $asset->location }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $asset->maintenance_schedules_count }}</flux:table.cell>
                        <flux:table.cell align="end">
                            @can('update', $asset)
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" :aria-label="__('Actions')" />
                                    <flux:menu>
                                        <flux:menu.item icon="pencil-square" wire:click="edit({{ $asset->id }})">{{ __('Edit') }}</flux:menu.item>
                                        <flux:menu.item icon="trash" variant="danger" wire:click="delete({{ $asset->id }})" wire:confirm="{{ __('Delete :name?', ['name' => $asset->name]) }}">{{ __('Delete') }}</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            @endcan
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="asset-form" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingAssetId ? __('Edit asset') : __('Add asset') }}</flux:heading>

            <flux:input wire:model="name" :label="__('Name')" required />

            <flux:select wire:model="category" :label="__('Category')">
                <flux:select.option value="">{{ __('Choose a category') }}</flux:select.option>
                @foreach ($this->categories() as $option)
                    <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="location" :label="__('Location')" />
            <flux:input wire:model="install_date" :label="__('Install date')" type="date" />
            <flux:textarea wire:model="notes" :label="__('Notes')" rows="2" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>

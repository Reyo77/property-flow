<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Entry authorizations') }}</flux:heading>
            <flux:subheading>{{ $community->name }}</flux:subheading>
        </div>

        @can('create', [App\Models\EntryAuthorization::class, $community])
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add authorization') }}</flux:button>
        @endcan
    </div>

    @if ($this->authorizations->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <flux:heading>{{ __('No entry authorizations yet') }}</flux:heading>
        </div>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Unit') }}</flux:table.column>
                <flux:table.column>{{ __('Relationship') }}</flux:table.column>
                <flux:table.column>{{ __('Phone') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->authorizations as $authorization)
                    <flux:table.row :key="$authorization->id">
                        <flux:table.cell variant="strong">{{ $authorization->name }}</flux:table.cell>
                        <flux:table.cell>{{ ($authorization->unit->building?->name.' · ') ?: '' }}{{ $authorization->unit->number }}</flux:table.cell>
                        <flux:table.cell>{{ $authorization->relationship }}</flux:table.cell>
                        <flux:table.cell>{{ $authorization->phone }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$authorization->active ? 'green' : 'zinc'">
                                {{ $authorization->active ? __('Active') : __('Inactive') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            @can('update', $authorization)
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" :aria-label="__('Actions')" />
                                    <flux:menu>
                                        <flux:menu.item icon="pencil-square" wire:click="edit({{ $authorization->id }})">{{ __('Edit') }}</flux:menu.item>
                                        <flux:menu.item icon="trash" variant="danger" wire:click="delete({{ $authorization->id }})" wire:confirm="{{ __('Remove :name?', ['name' => $authorization->name]) }}">{{ __('Delete') }}</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            @endcan
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="entry-authorization-form" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingId ? __('Edit authorization') : __('Add authorization') }}</flux:heading>

            <flux:input wire:model="name" :label="__('Name')" required />

            <flux:select wire:model="unit_id" :label="__('Unit')">
                <flux:select.option value="">{{ __('Choose a unit') }}</flux:select.option>
                @foreach ($this->units as $unit)
                    <flux:select.option :value="$unit->id">{{ ($unit->building?->name.' · ') ?: '' }}{{ $unit->number }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="relationship" :label="__('Relationship (optional)')" placeholder="e.g. Cleaner" />
            <flux:input wire:model="phone" :label="__('Phone (optional)')" />
            <flux:textarea wire:model="notes" :label="__('Notes (optional)')" rows="2" />
            <flux:checkbox wire:model="active" :label="__('Active')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>

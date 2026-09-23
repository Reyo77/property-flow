<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Vendors') }}</flux:heading>
            <flux:subheading>{{ __('Outside contractors you assign work orders to.') }}</flux:subheading>
        </div>

        @can('create', App\Models\Vendor::class)
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add vendor') }}</flux:button>
        @endcan
    </div>

    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search name, trade or email')" class="max-w-xs" />

    @if ($this->vendors->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <flux:heading>{{ __('No vendors yet') }}</flux:heading>
        </div>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Trade') }}</flux:table.column>
                <flux:table.column>{{ __('Contact') }}</flux:table.column>
                <flux:table.column>{{ __('Portal') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->vendors as $vendor)
                    <flux:table.row :key="$vendor->id">
                        <flux:table.cell variant="strong">{{ $vendor->name }}</flux:table.cell>
                        <flux:table.cell>{{ $vendor->trade }}</flux:table.cell>
                        <flux:table.cell>
                            <div>{{ $vendor->email }}</div>
                            <div class="text-xs">{{ $vendor->phone }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($vendor->hasPortalAccess())
                                <flux:badge size="sm" color="green">{{ __('Active') }}</flux:badge>
                            @else
                                <flux:badge size="sm">{{ __('No login') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            @can('update', $vendor)
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" :aria-label="__('Actions')" />
                                    <flux:menu>
                                        <flux:menu.item icon="pencil-square" wire:click="edit({{ $vendor->id }})">{{ __('Edit') }}</flux:menu.item>
                                        @can('invite', $vendor)
                                            <flux:menu.item icon="link" wire:click="invite({{ $vendor->id }})">{{ __('Invite to portal') }}</flux:menu.item>
                                        @endcan
                                        <flux:menu.item icon="trash" variant="danger" wire:click="delete({{ $vendor->id }})" wire:confirm="{{ __('Delete :name?', ['name' => $vendor->name]) }}">{{ __('Delete') }}</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            @endcan
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="vendor-form" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingVendorId ? __('Edit vendor') : __('Add vendor') }}</flux:heading>

            <flux:input wire:model="name" :label="__('Name')" required />
            <flux:input wire:model="trade" :label="__('Trade')" :placeholder="__('e.g. Plumbing, Electrical')" />
            <flux:input wire:model="phone" :label="__('Phone')" />
            <flux:input wire:model="email" :label="__('Email')" type="email" :description="__('Needed to invite them to the vendor portal.')" />
            <flux:textarea wire:model="notes" :label="__('Notes')" rows="2" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    @include('livewire.partials.invitation-link-modal')
</section>

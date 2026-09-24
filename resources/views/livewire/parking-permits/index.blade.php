<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Parking permits') }}</flux:heading>
            <flux:subheading>{{ $community->name }}</flux:subheading>
        </div>

        @if ($this->canManage())
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Issue permit') }}</flux:button>
        @endif
    </div>

    @if ($this->permits->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <flux:heading>{{ __('No parking permits') }}</flux:heading>
        </div>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Plate') }}</flux:table.column>
                <flux:table.column>{{ __('Unit') }}</flux:table.column>
                <flux:table.column>{{ __('Visitor') }}</flux:table.column>
                <flux:table.column>{{ __('Dates') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->permits as $permit)
                    <flux:table.row :key="$permit->id">
                        <flux:table.cell variant="strong">{{ $permit->plate_number }}</flux:table.cell>
                        <flux:table.cell>{{ ($permit->unit->building?->name.' · ') ?: '' }}{{ $permit->unit->number }}</flux:table.cell>
                        <flux:table.cell>{{ $permit->visitor_name }}</flux:table.cell>
                        <flux:table.cell>{{ $permit->starts_on->toFormattedDateString() }} &ndash; {{ $permit->ends_on->toFormattedDateString() }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$permit->isActive() ? 'green' : 'zinc'">
                                {{ $permit->isActive() ? __('Active') : __('Expired') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            @can('delete', $permit)
                                <flux:button size="sm" variant="danger" wire:click="delete({{ $permit->id }})" wire:confirm="{{ __('Revoke this permit?') }}">{{ __('Revoke') }}</flux:button>
                            @endcan
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="parking-permit-form" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ __('Issue parking permit') }}</flux:heading>

            <flux:select wire:model="unit_id" :label="__('Unit')">
                <flux:select.option value="">{{ __('Choose a unit') }}</flux:select.option>
                @foreach ($this->units as $unit)
                    <flux:select.option :value="$unit->id">{{ ($unit->building?->name.' · ') ?: '' }}{{ $unit->number }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="plate_number" :label="__('License plate')" required />
            <flux:input wire:model="visitor_name" :label="__('Visitor name (optional)')" />

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="starts_on" :label="__('From')" type="date" required />
                <flux:input wire:model="ends_on" :label="__('To')" type="date" required />
            </div>

            <flux:textarea wire:model="notes" :label="__('Notes (optional)')" rows="2" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Issue permit') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>

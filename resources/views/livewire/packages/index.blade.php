<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Packages') }}</flux:heading>
            <flux:subheading>{{ $community->name }}</flux:subheading>
        </div>

        @if ($this->canManage())
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Log package') }}</flux:button>
        @endif
    </div>

    @if ($this->canManage())
        <flux:radio.group wire:model.live="statusFilter" variant="segmented">
            <flux:radio value="awaiting_pickup" :label="__('Awaiting pickup')" />
            <flux:radio value="picked_up" :label="__('Picked up')" />
            <flux:radio value="" :label="__('All')" />
        </flux:radio.group>
    @endif

    @if ($this->packages->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <flux:heading>{{ __('No packages') }}</flux:heading>
        </div>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Carrier') }}</flux:table.column>
                <flux:table.column>{{ __('For') }}</flux:table.column>
                <flux:table.column>{{ __('Shelf') }}</flux:table.column>
                <flux:table.column>{{ __('Logged') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->packages as $package)
                    <flux:table.row :key="$package->id">
                        <flux:table.cell variant="strong">
                            {{ $package->carrier }}
                            @if ($package->tracking_number)
                                <flux:text class="text-xs">{{ $package->tracking_number }}</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($package->unit)
                                {{ ($package->unit->building?->name.' · ') ?: '' }}{{ __('Unit :number', ['number' => $package->unit->number]) }}
                            @endif
                            @if ($package->resident)
                                <flux:text class="text-xs">{{ $package->resident->name }}</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $package->shelf_location }}</flux:table.cell>
                        <flux:table.cell>{{ $package->created_at?->diffForHumans() }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$package->status === App\Enums\PackageStatus::AwaitingPickup ? 'blue' : 'zinc'">
                                {{ $package->status->label() }}
                            </flux:badge>
                            @if ($package->released_to_name)
                                <flux:text class="text-xs">{{ __('to :name', ['name' => $package->released_to_name]) }}</flux:text>
                            @endif
                            @if ($package->signature_disk_path)
                                <flux:link :href="route('communities.packages.signature', [$community, $package])" target="_blank" class="text-xs">{{ __('View signature') }}</flux:link>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            @can('release', $package)
                                @if ($package->status === App\Enums\PackageStatus::AwaitingPickup)
                                    <flux:button size="sm" wire:click="openRelease({{ $package->id }})">{{ __('Release') }}</flux:button>
                                @endif
                            @endcan
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="package-form" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ __('Log package') }}</flux:heading>

            <flux:input wire:model="carrier" :label="__('Carrier')" required />
            <flux:input wire:model="tracking_number" :label="__('Tracking number (optional)')" />
            <flux:input wire:model="shelf_location" :label="__('Shelf location (optional)')" />

            <flux:select wire:model.live="unit_id" :label="__('Unit (optional)')">
                <flux:select.option value="">{{ __('Unknown') }}</flux:select.option>
                @foreach ($this->units as $unit)
                    <flux:select.option :value="$unit->id">{{ ($unit->building?->name.' · ') ?: '' }}{{ $unit->number }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($this->residentsForUnit->isNotEmpty())
                <flux:select wire:model="resident_id" :label="__('Resident (optional)')">
                    <flux:select.option value="">{{ __('Unspecified') }}</flux:select.option>
                    @foreach ($this->residentsForUnit as $resident)
                        <flux:select.option :value="$resident->id">{{ $resident->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Log package') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="release-form" class="w-full max-w-lg">
        <form wire:submit="release" class="space-y-5">
            <flux:heading size="lg">{{ __('Release package') }}</flux:heading>

            <flux:input wire:model="released_to_name" :label="__('Released to')" required />

            <x-signature-pad model="signature" :label="__('Signature (optional)')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Release') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>

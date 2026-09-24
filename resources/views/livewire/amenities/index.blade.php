@php
    $weekdayLabels = [0 => __('Sun'), 1 => __('Mon'), 2 => __('Tue'), 3 => __('Wed'), 4 => __('Thu'), 5 => __('Fri'), 6 => __('Sat')];
@endphp

<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Amenities') }}</flux:heading>
            <flux:subheading>{{ $community->name }}</flux:subheading>
        </div>

        @if ($this->canManage())
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add amenity') }}</flux:button>
        @endif
    </div>

    @if ($this->amenities->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <flux:heading>{{ __('No amenities yet') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Add a bookable space like a party room or guest suite.') }}</flux:text>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->amenities as $amenity)
                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" wire:key="amenity-{{ $amenity->id }}">
                    <div class="flex items-start justify-between gap-2">
                        <flux:heading>
                            <flux:link :href="route('communities.amenities.show', [$community, $amenity])" wire:navigate>{{ $amenity->name }}</flux:link>
                        </flux:heading>
                        @unless ($amenity->active)
                            <flux:badge color="zinc" size="sm">{{ __('Inactive') }}</flux:badge>
                        @endunless
                    </div>

                    <flux:text class="mt-1 text-sm">
                        {{ sprintf('%02d:%02d', intdiv($amenity->opens_at_minutes, 60), $amenity->opens_at_minutes % 60) }}
                        &ndash;
                        {{ sprintf('%02d:%02d', intdiv($amenity->closes_at_minutes, 60), $amenity->closes_at_minutes % 60) }}
                        @if ($amenity->fee_cents)
                            · {{ __(':amount fee', ['amount' => '$'.number_format($amenity->fee_cents / 100, 2)]) }}
                        @endif
                    </flux:text>

                    @if ($amenity->description)
                        <flux:text class="mt-2">{{ $amenity->description }}</flux:text>
                    @endif

                    @if ($this->canManage())
                        <div class="mt-4 flex justify-end gap-2">
                            <flux:button size="sm" icon="pencil-square" wire:click="edit({{ $amenity->id }})">{{ __('Edit') }}</flux:button>
                            <flux:button size="sm" variant="danger" icon="trash" wire:click="delete({{ $amenity->id }})" wire:confirm="{{ __('Delete :name?', ['name' => $amenity->name]) }}">{{ __('Delete') }}</flux:button>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <flux:modal name="amenity-form" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingAmenityId ? __('Edit amenity') : __('Add amenity') }}</flux:heading>

            <flux:input wire:model="name" :label="__('Name')" required />
            <flux:textarea wire:model="description" :label="__('Description')" rows="2" />
            <flux:input wire:model="location" :label="__('Location')" />

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="opens_at" :label="__('Opens')" type="time" required />
                <flux:input wire:model="closes_at" :label="__('Closes')" type="time" required />
            </div>

            <flux:checkbox.group wire:model="closed_weekdays" :label="__('Closed on')">
                @foreach ($weekdayLabels as $day => $label)
                    <flux:checkbox :value="$day" :label="$label" />
                @endforeach
            </flux:checkbox.group>

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="slot_minutes" :label="__('Slot length (minutes)')" type="number" min="5" required />
                <flux:input wire:model="capacity" :label="__('Capacity per slot')" type="number" min="1" required />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="advance_booking_days" :label="__('Max days in advance (optional)')" type="number" min="0" />
                <flux:input wire:model="min_notice_hours" :label="__('Min notice, hours (optional)')" type="number" min="0" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="max_bookings_per_unit" :label="__('Max bookings per unit (optional)')" type="number" min="1" />
                <flux:input wire:model="max_bookings_period_days" :label="__('...within days (optional)')" type="number" min="1" />
            </div>

            <flux:input wire:model="cancellation_notice_hours" :label="__('Cancellation notice, hours (optional)')" type="number" min="0" />

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="fee" :label="__('Fee, $ (optional)')" type="number" step="0.01" min="0" />
                <flux:input wire:model="deposit" :label="__('Deposit, $ (optional)')" type="number" step="0.01" min="0" />
            </div>

            <flux:textarea wire:model="terms" :label="__('Terms (optional, shown for acceptance when booking)')" rows="2" />

            <flux:checkbox wire:model="needs_approval" :label="__('Bookings need manager approval')" />
            <flux:checkbox wire:model="active" :label="__('Active (bookable)')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>

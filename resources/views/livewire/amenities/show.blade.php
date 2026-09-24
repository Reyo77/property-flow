@php
    $timezone = $community->timezone;
@endphp

<section class="w-full max-w-4xl space-y-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <flux:heading size="xl" level="1">{{ $amenity->name }}</flux:heading>
                @unless ($amenity->active)
                    <flux:badge color="zinc">{{ __('Inactive') }}</flux:badge>
                @endunless
            </div>
            <flux:subheading>
                {{ sprintf('%02d:%02d', intdiv($amenity->opens_at_minutes, 60), $amenity->opens_at_minutes % 60) }}
                &ndash;
                {{ sprintf('%02d:%02d', intdiv($amenity->closes_at_minutes, 60), $amenity->closes_at_minutes % 60) }}
                · {{ __(':minutes-minute slots', ['minutes' => $amenity->slot_minutes]) }}
                @if ($amenity->fee_cents)
                    · {{ __('Fee: :amount', ['amount' => '$'.number_format($amenity->fee_cents / 100, 2)]) }}
                @endif
                @if ($amenity->deposit_cents)
                    · {{ __('Deposit: :amount', ['amount' => '$'.number_format($amenity->deposit_cents / 100, 2)]) }}
                @endif
            </flux:subheading>
            @if ($amenity->description)
                <flux:text class="mt-2">{{ $amenity->description }}</flux:text>
            @endif
        </div>
    </div>

    @php($canBook = auth()->user()->can('book', $amenity))

    <div class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
        <div class="flex flex-wrap items-end gap-4">
            <flux:input
                wire:model.live="date"
                :label="__('Date')"
                type="date"
                min="{{ $amenity->minBookableDate()->toDateString() }}"
                max="{{ $amenity->maxBookableDate()?->toDateString() }}"
            />
        </div>

        @if ($this->availableSlots === [])
            <flux:text>{{ __('No slots available on this date.') }}</flux:text>
        @else
            <div class="flex flex-wrap gap-2">
                @foreach ($this->availableSlots as $slot)
                    <flux:button
                        size="sm"
                        :variant="$slot['bookable'] ? 'filled' : 'ghost'"
                        :disabled="! $slot['bookable'] || ! $canBook"
                        wire:click="selectSlot('{{ $slot['starts_at']->toIso8601String() }}')"
                    >
                        {{ $slot['starts_at']->clone()->setTimezone($timezone)->format('g:ia') }}
                        @if (! $slot['bookable'])
                            ({{ __('Full') }})
                        @endif
                    </flux:button>
                @endforeach
            </div>
        @endif
    </div>

    @if ($this->myBookings->isNotEmpty())
        <div class="space-y-3">
            <flux:heading size="lg">{{ __('My bookings') }}</flux:heading>
            <div class="space-y-2">
                @foreach ($this->myBookings as $booking)
                    <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" wire:key="my-booking-{{ $booking->id }}">
                        <div>
                            <flux:text class="font-medium text-zinc-800 dark:text-zinc-100">
                                {{ $booking->starts_at->clone()->setTimezone($timezone)->format('D, M j · g:ia') }}
                            </flux:text>
                            <div>
                                <flux:badge size="sm" :color="$booking->status === App\Enums\AmenityBookingStatus::Pending ? 'amber' : 'blue'">
                                    {{ $booking->status->label() }}
                                </flux:badge>
                            </div>
                        </div>
                        @can('cancel', $booking)
                            <flux:button size="sm" variant="danger" wire:click="cancelBooking({{ $booking->id }})" wire:confirm="{{ __('Cancel this booking?') }}">
                                {{ __('Cancel') }}
                            </flux:button>
                        @endcan
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if ($this->canManage())
        <div class="space-y-3">
            <flux:heading size="lg">{{ __('Bookings to manage') }}</flux:heading>

            @if ($this->managedBookings->isEmpty())
                <flux:text>{{ __('No upcoming bookings.') }}</flux:text>
            @else
                <div class="space-y-2">
                    @foreach ($this->managedBookings as $booking)
                        <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" wire:key="managed-booking-{{ $booking->id }}">
                            <div>
                                <flux:text class="font-medium text-zinc-800 dark:text-zinc-100">
                                    {{ $booking->starts_at->clone()->setTimezone($timezone)->format('D, M j · g:ia') }}
                                </flux:text>
                                <flux:text class="text-sm">
                                    {{ $booking->unit ? (($booking->unit->building?->name.' · ') ?: '').__('Unit :number', ['number' => $booking->unit->number]) : $booking->bookedBy->name }}
                                </flux:text>
                                <div>
                                    <flux:badge size="sm" :color="$booking->status === App\Enums\AmenityBookingStatus::Pending ? 'amber' : 'blue'">
                                        {{ $booking->status->label() }}
                                    </flux:badge>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                @if ($booking->status === App\Enums\AmenityBookingStatus::Pending)
                                    <flux:button size="sm" wire:click="decide({{ $booking->id }}, 'confirmed')">{{ __('Approve') }}</flux:button>
                                    <flux:button size="sm" variant="danger" wire:click="decide({{ $booking->id }}, 'rejected')">{{ __('Reject') }}</flux:button>
                                @else
                                    <flux:button size="sm" variant="danger" wire:click="cancelBooking({{ $booking->id }})" wire:confirm="{{ __('Cancel this booking?') }}">{{ __('Cancel') }}</flux:button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="space-y-3">
            <flux:heading size="lg">{{ __('Blackout dates') }}</flux:heading>

            @if ($this->blackouts->isNotEmpty())
                <div class="space-y-2">
                    @foreach ($this->blackouts as $blackout)
                        <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" wire:key="blackout-{{ $blackout->id }}">
                            <flux:text>
                                {{ $blackout->starts_on->toFormattedDateString() }} &ndash; {{ $blackout->ends_on->toFormattedDateString() }}
                                @if ($blackout->reason)
                                    · {{ $blackout->reason }}
                                @endif
                            </flux:text>
                            <flux:button size="sm" variant="danger" icon="trash" wire:click="deleteBlackout({{ $blackout->id }})" />
                        </div>
                    @endforeach
                </div>
            @endif

            <form wire:submit="addBlackout" class="flex flex-wrap items-end gap-3">
                <flux:input wire:model="blackout_starts_on" :label="__('From')" type="date" />
                <flux:input wire:model="blackout_ends_on" :label="__('To')" type="date" />
                <flux:input wire:model="blackout_reason" :label="__('Reason (optional)')" />
                <flux:button type="submit" size="sm">{{ __('Add blackout') }}</flux:button>
            </form>
        </div>
    @endif

    <flux:modal name="booking-form" class="w-full max-w-lg">
        <form wire:submit="book" class="space-y-5">
            <flux:heading size="lg">{{ __('Book :name', ['name' => $amenity->name]) }}</flux:heading>

            @if ($selectedSlot !== '')
                <flux:text>{{ \Illuminate\Support\Carbon::parse($selectedSlot)->setTimezone($timezone)->format('D, M j · g:ia') }}</flux:text>
            @endif

            @error('slot')
                <flux:callout variant="danger" icon="exclamation-triangle"><flux:callout.text>{{ $message }}</flux:callout.text></flux:callout>
            @enderror

            @if ($this->canPickAnyUnit())
                <flux:select wire:model="unit_id" :label="__('Unit')">
                    <flux:select.option value="">{{ __('None') }}</flux:select.option>
                    @foreach ($this->communityUnits as $unit)
                        <flux:select.option :value="$unit->id">{{ ($unit->building?->name.' · ') ?: '' }}{{ $unit->number }}</flux:select.option>
                    @endforeach
                </flux:select>
            @elseif ($this->myUnits->count() > 1)
                <flux:select wire:model="unit_id" :label="__('Unit')">
                    <flux:select.option value="">{{ __('Choose a unit') }}</flux:select.option>
                    @foreach ($this->myUnits as $unit)
                        <flux:select.option :value="$unit->id">{{ ($unit->building?->name.' · ') ?: '' }}{{ $unit->number }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:textarea wire:model="notes" :label="__('Notes (optional)')" rows="2" />

            @if ($amenity->terms)
                <flux:callout icon="document-text">
                    <flux:callout.text class="whitespace-pre-line">{{ $amenity->terms }}</flux:callout.text>
                </flux:callout>
                <flux:checkbox wire:model="terms_accepted" :label="__('I agree to the terms above')" />
            @endif

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Book') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>

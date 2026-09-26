<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Events') }}</flux:heading>
            <flux:subheading>{{ $community->name }}</flux:subheading>
        </div>

        @can('create', [App\Models\Event::class, $community])
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('New event') }}</flux:button>
        @endcan
    </div>

    <flux:radio.group wire:model.live="tab" variant="segmented">
        <flux:radio value="upcoming" :label="__('Upcoming')" />
        <flux:radio value="past" :label="__('Past')" />
    </flux:radio.group>

    @if ($this->events->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <flux:heading>{{ $tab === 'upcoming' ? __('No upcoming events') : __('No past events') }}</flux:heading>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($this->events as $event)
                @php($myStatus = $this->myRsvpStatus($event))
                @php($counts = $this->rsvpCounts($event))
                <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700" wire:key="event-{{ $event->id }}">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <flux:heading size="lg">{{ $event->title }}</flux:heading>
                            <flux:text class="mt-1">
                                {{ App\Support\LocalTime::local($event->starts_at, $community)->toFormattedDateString() }}, {{ App\Support\LocalTime::local($event->starts_at, $community)->format('g:i A') }} – {{ App\Support\LocalTime::local($event->ends_at, $community)->format('g:i A') }}
                            </flux:text>
                            @if ($event->location)
                                <flux:text>{{ $event->location }}</flux:text>
                            @endif
                        </div>

                        @can('update', $event)
                            <flux:dropdown position="bottom" align="end">
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" :aria-label="__('Actions')" />
                                <flux:menu>
                                    <flux:menu.item icon="pencil-square" wire:click="edit({{ $event->id }})">{{ __('Edit') }}</flux:menu.item>
                                    <flux:menu.item icon="trash" variant="danger" wire:click="delete({{ $event->id }})" wire:confirm="{{ __('Delete :title?', ['title' => $event->title]) }}">{{ __('Delete') }}</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        @endcan
                    </div>

                    @if ($event->description)
                        <flux:text class="mt-3">{{ $event->description }}</flux:text>
                    @endif

                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                        <flux:text class="text-sm">
                            {{ __(':going going · :maybe maybe · :not not going', ['going' => $counts['going'], 'maybe' => $counts['maybe'], 'not' => $counts['not_going']]) }}
                        </flux:text>

                        @if ($tab === 'upcoming')
                            <div class="flex gap-1" wire:key="rsvp-{{ $event->id }}">
                                @foreach (App\Enums\RsvpStatus::cases() as $status)
                                    <flux:button
                                        size="sm"
                                        :variant="$myStatus === $status ? 'primary' : 'filled'"
                                        wire:click="rsvp({{ $event->id }}, '{{ $status->value }}')"
                                    >
                                        {{ $status->label() }}
                                    </flux:button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <flux:modal name="event-form" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingEventId ? __('Edit event') : __('New event') }}</flux:heading>

            <flux:input wire:model="title" :label="__('Title')" required />
            <flux:textarea wire:model="description" :label="__('Description')" rows="3" />
            <flux:input wire:model="location" :label="__('Location')" />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="starts_at" :label="__('Starts')" type="datetime-local" required />
                <flux:input wire:model="ends_at" :label="__('Ends')" type="datetime-local" required />
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

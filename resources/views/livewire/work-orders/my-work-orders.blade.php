<section class="w-full max-w-4xl space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('My work orders') }}</flux:heading>
        <flux:subheading>{{ __('Open jobs assigned to you, across every community.') }}</flux:subheading>
    </div>

    <div class="space-y-4">
        @forelse ($this->workOrders as $workOrder)
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" wire:key="work-order-{{ $workOrder->id }}">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <flux:heading>{{ $workOrder->title }}</flux:heading>
                        <flux:subheading>
                            {{ $workOrder->community->name }}
                            @if ($workOrder->serviceRequest?->unit)
                                · {{ ($workOrder->serviceRequest->unit->building?->name.' · ') ?: '' }}{{ __('Unit :number', ['number' => $workOrder->serviceRequest->unit->number]) }}
                            @endif
                            @if ($workOrder->due_on)
                                · {{ __('due :date', ['date' => $workOrder->due_on->toFormattedDateString()]) }}
                            @endif
                        </flux:subheading>
                    </div>
                    <flux:badge :color="$workOrder->status->isFinal() ? 'zinc' : 'blue'">{{ $workOrder->status->label() }}</flux:badge>
                </div>

                @if ($workOrder->description)
                    <flux:text class="mt-2 whitespace-pre-line">{{ $workOrder->description }}</flux:text>
                @endif

                @php($nextStatuses = $this->nextStatuses($workOrder))
                @if ($nextStatuses !== [])
                    <div class="mt-4 space-y-2">
                        @if (in_array(App\Enums\WorkOrderStatus::Completed, $nextStatuses, true))
                            <flux:textarea wire:model="completionNotes.{{ $workOrder->id }}" :label="__('Completion notes (optional)')" rows="2" />
                        @endif
                        <div class="flex flex-wrap gap-2">
                            @foreach ($nextStatuses as $nextStatus)
                                <flux:button size="sm" wire:click="transitionTo({{ $workOrder->id }}, '{{ $nextStatus->value }}')">
                                    {{ __('Mark :status', ['status' => $nextStatus->label()]) }}
                                </flux:button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <flux:text>{{ __('No open work orders assigned to you.') }}</flux:text>
        @endforelse
    </div>
</section>

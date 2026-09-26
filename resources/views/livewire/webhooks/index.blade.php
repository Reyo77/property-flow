<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Webhooks') }}</flux:heading>
            <flux:subheading>{{ __('Send events to your other systems as they happen. Each delivery is signed so the receiver can trust it.') }}</flux:subheading>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add webhook') }}</flux:button>
    </div>

    @if ($revealedSecret !== null)
        <flux:callout icon="key" :heading="__('Signing secret')">
            <flux:callout.text>
                {{ __('Use this to check the PropertyFlow-Signature header on each delivery. Keep it private.') }}
                <code class="mt-2 block select-all break-all rounded bg-zinc-100 p-2 font-mono text-sm dark:bg-zinc-800" data-test="secret">{{ $revealedSecret }}</code>
            </flux:callout.text>
            <x-slot name="controls">
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="hideSecret" :aria-label="__('Hide')" />
            </x-slot>
        </flux:callout>
    @endif

    @if ($this->endpoints->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <flux:heading>{{ __('No webhooks yet') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Add an https:// address and choose which events to send to it.') }}</flux:text>
        </div>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Endpoint') }}</flux:table.column>
                <flux:table.column>{{ __('Events') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Last delivered') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->endpoints as $endpoint)
                    <flux:table.row :key="$endpoint->id">
                        <flux:table.cell>
                            <div class="font-medium break-all text-zinc-800 dark:text-white">{{ $endpoint->url }}</div>
                            @if ($endpoint->description)
                                <div class="text-xs">{{ $endpoint->description }}</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ trans_choice(':count event|:count events', count($endpoint->events)) }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($endpoint->is_active)
                                <flux:badge size="sm" color="green">{{ __('Active') }}</flux:badge>
                                @if ($endpoint->consecutive_failures > 0)
                                    <div class="mt-1 text-xs text-amber-600">{{ trans_choice(':count failure in a row|:count failures in a row', $endpoint->consecutive_failures) }}</div>
                                @endif
                            @elseif ($endpoint->consecutive_failures > 0)
                                <flux:badge size="sm" color="red">{{ __('Switched off after failures') }}</flux:badge>
                            @else
                                <flux:badge size="sm">{{ __('Off') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $endpoint->last_delivered_at?->diffForHumans() ?? __('Never') }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" :aria-label="__('Actions')" />
                                <flux:menu>
                                    <flux:menu.item icon="pencil-square" wire:click="edit({{ $endpoint->id }})">{{ __('Edit') }}</flux:menu.item>
                                    <flux:menu.item icon="paper-airplane" wire:click="sendTest({{ $endpoint->id }})">{{ __('Send test') }}</flux:menu.item>
                                    <flux:menu.item icon="queue-list" wire:click="showDeliveries({{ $endpoint->id }})">{{ __('Recent deliveries') }}</flux:menu.item>
                                    <flux:menu.item icon="key" wire:click="revealSecret({{ $endpoint->id }})">{{ __('Show signing secret') }}</flux:menu.item>
                                    <flux:menu.item icon="arrow-path" wire:click="rotateSecret({{ $endpoint->id }})" wire:confirm="{{ __('Create a new signing secret? The current one stops working immediately.') }}">{{ __('Rotate secret') }}</flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger" wire:click="delete({{ $endpoint->id }})" wire:confirm="{{ __('Delete this webhook? Deliveries stop immediately.') }}">{{ __('Delete') }}</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    @if ($selectedEndpointId !== null)
        <div class="space-y-3" data-test="deliveries">
            <flux:heading size="lg">{{ __('Recent deliveries') }}</flux:heading>
            @if ($this->deliveries->isEmpty())
                <flux:text>{{ __('Nothing sent yet.') }}</flux:text>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Event') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column>{{ __('Response') }}</flux:table.column>
                        <flux:table.column>{{ __('When') }}</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->deliveries as $delivery)
                            <flux:table.row :key="$delivery->id">
                                <flux:table.cell><code class="text-xs">{{ $delivery->event }}</code></flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm" :color="$delivery->status->color()">{{ $delivery->status->label() }}</flux:badge>
                                    <div class="mt-1 text-xs">{{ trans_choice(':count attempt|:count attempts', $delivery->attempts) }}</div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <div>{{ $delivery->response_status ?? '—' }}</div>
                                    @if ($delivery->response_excerpt)
                                        <div class="max-w-xs truncate text-xs" title="{{ $delivery->response_excerpt }}">{{ $delivery->response_excerpt }}</div>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>{{ $delivery->created_at?->diffForHumans() }}</flux:table.cell>
                                <flux:table.cell align="end">
                                    @if ($delivery->status === App\Enums\WebhookDeliveryStatus::Failed)
                                        <flux:button size="sm" wire:click="redeliver({{ $delivery->id }})">{{ __('Send again') }}</flux:button>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </div>
    @endif

    <flux:modal name="endpoint-form" class="w-full max-w-2xl">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingEndpointId ? __('Edit webhook') : __('Add webhook') }}</flux:heading>
            <flux:input wire:model="url" type="url" :label="__('Endpoint URL')" placeholder="https://example.com/webhooks/propertyflow" required />
            <flux:input wire:model="description" :label="__('Description (optional)')" :placeholder="__('e.g. Accounting system')" />

            <flux:checkbox.group wire:model="events" :label="__('Events to send')">
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($this->eventGroups() as $group => $groupEvents)
                        <div class="space-y-2">
                            <flux:text variant="strong">{{ $group }}</flux:text>
                            @foreach ($groupEvents as $event)
                                <flux:checkbox :value="$event->value" :label="$event->label()" :description="$event->value" />
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </flux:checkbox.group>

            @if ($editingEndpointId)
                <flux:switch wire:model="active" :label="__('Active')" :description="__('Turning it back on clears its failure count.')" />
            @endif

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>

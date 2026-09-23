<section class="w-full max-w-4xl space-y-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <flux:heading size="xl" level="1">{{ $serviceRequest->title }}</flux:heading>
                <flux:badge :color="$serviceRequest->status === App\Enums\ServiceRequestStatus::Closed ? 'zinc' : 'blue'">
                    {{ $serviceRequest->status->label() }}
                </flux:badge>
                @if ($serviceRequest->isOverdue())
                    <flux:badge color="red">{{ __('Overdue') }}</flux:badge>
                @endif
            </div>
            <flux:subheading>
                {{ $serviceRequest->category->label() }} · {{ $serviceRequest->priority->label() }} {{ __('priority') }}
                @if ($serviceRequest->unit)
                    · {{ ($serviceRequest->unit->building?->name.' · ') ?: '' }}{{ __('Unit :number', ['number' => $serviceRequest->unit->number]) }}
                @else
                    · {{ __('Common area') }}
                @endif
            </flux:subheading>
        </div>

        @if ($this->canManage() && $this->nextStatuses() !== [])
            <div class="flex flex-wrap gap-2">
                @foreach ($this->nextStatuses() as $nextStatus)
                    <flux:button size="sm" wire:click="transitionTo('{{ $nextStatus->value }}')" wire:confirm="{{ __('Move to :status?', ['status' => $nextStatus->label()]) }}">
                        {{ __('Mark :status', ['status' => $nextStatus->label()]) }}
                    </flux:button>
                @endforeach
            </div>
        @endif
    </div>

    <flux:text class="whitespace-pre-line">{{ $serviceRequest->description }}</flux:text>

    @if ($serviceRequest->entry_permission)
        <flux:callout icon="key" variant="secondary">
            <flux:callout.text>{{ __('Staff or the vendor may enter the unit if nobody is home.') }}</flux:callout.text>
        </flux:callout>
    @endif

    @if ($serviceRequest->attachments->isNotEmpty())
        <div class="space-y-2">
            <flux:heading size="lg">{{ __('Photos') }}</flux:heading>
            <div class="flex flex-wrap gap-3">
                @foreach ($serviceRequest->attachments as $attachment)
                    <a href="{{ route('communities.attachments.download', [$community, $attachment]) }}" target="_blank" class="block size-24 overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <img src="{{ route('communities.attachments.download', [$community, $attachment]) }}" alt="{{ $attachment->original_filename }}" class="size-full object-cover" />
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">{{ __('Work order') }}</flux:heading>
            @can('create', [App\Models\WorkOrder::class, $community])
                @if ($this->canManage() && ! $this->workOrder())
                    <flux:button size="sm" icon="wrench" wire:click="openWorkOrderForm">{{ __('Create work order') }}</flux:button>
                @endif
            @endcan
        </div>

        @if ($workOrder = $this->workOrder())
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <flux:heading>{{ $workOrder->title }}</flux:heading>
                        <flux:text class="text-sm">
                            {{ __('Assigned to') }}
                            {{ $workOrder->assignedUser?->name ?? $workOrder->assignedVendor?->name ?? __('nobody yet') }}
                            @if ($workOrder->due_on)
                                · {{ __('due :date', ['date' => $workOrder->due_on->toFormattedDateString()]) }}
                            @endif
                        </flux:text>
                    </div>
                    <flux:badge :color="$workOrder->status->isFinal() ? 'zinc' : 'blue'">{{ $workOrder->status->label() }}</flux:badge>
                </div>

                @if ($workOrder->description)
                    <flux:text class="mt-2">{{ $workOrder->description }}</flux:text>
                @endif

                @if ($workOrder->completion_notes)
                    <flux:text class="mt-2 text-sm"><strong>{{ __('Completion notes:') }}</strong> {{ $workOrder->completion_notes }}</flux:text>
                @endif

                @can('updateProgress', $workOrder)
                    @php($nextWorkOrderStatuses = $workOrder->status->allowedNextStatuses())
                    @if ($nextWorkOrderStatuses !== [])
                        <div class="mt-4 space-y-2">
                            @if (in_array(App\Enums\WorkOrderStatus::Completed, $nextWorkOrderStatuses, true))
                                <flux:textarea wire:model="completionNotes" :label="__('Completion notes (optional)')" rows="2" />
                            @endif
                            <div class="flex flex-wrap gap-2">
                                @foreach ($nextWorkOrderStatuses as $nextWorkOrderStatus)
                                    <flux:button size="sm" wire:click="transitionWorkOrderTo('{{ $nextWorkOrderStatus->value }}')">
                                        {{ __('Mark :status', ['status' => $nextWorkOrderStatus->label()]) }}
                                    </flux:button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endcan
            </div>
        @else
            <flux:text>{{ __('No work order yet.') }}</flux:text>
        @endif
    </div>

    <div class="space-y-4">
        <flux:heading size="lg">{{ __('Comments') }}</flux:heading>

        <div class="space-y-3">
            @forelse ($this->comments as $comment)
                <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" wire:key="comment-{{ $comment->id }}">
                    <div class="flex items-center justify-between">
                        <flux:text class="font-medium text-zinc-800 dark:text-zinc-100">{{ $comment->author?->name ?? __('Resident') }}</flux:text>
                        <div class="flex items-center gap-2">
                            @unless ($comment->visible_to_resident)
                                <flux:badge size="sm">{{ __('Internal') }}</flux:badge>
                            @endunless
                            <flux:text class="text-xs">{{ $comment->created_at?->diffForHumans() }}</flux:text>
                        </div>
                    </div>
                    <flux:text class="mt-1 whitespace-pre-line">{{ $comment->body }}</flux:text>
                </div>
            @empty
                <flux:text>{{ __('No comments yet.') }}</flux:text>
            @endforelse
        </div>

        <form wire:submit="postComment" class="space-y-3">
            <flux:textarea wire:model="body" :label="__('Add a comment')" rows="2" />
            @if ($this->canAddInternalComment())
                <flux:checkbox wire:model="commentIsInternal" :label="__('Internal note (not visible to the resident)')" />
            @endif
            <flux:button type="submit" size="sm">{{ __('Post comment') }}</flux:button>
        </form>
    </div>

    <flux:modal name="work-order-form" class="w-full max-w-lg">
        <form wire:submit="createWorkOrder" class="space-y-5">
            <flux:heading size="lg">{{ __('Create work order') }}</flux:heading>

            <flux:input wire:model="title" :label="__('Title')" required />
            <flux:textarea wire:model="description" :label="__('Notes')" rows="2" />

            <flux:radio.group wire:model.live="assignee_type" :label="__('Assign to')" variant="segmented">
                @foreach ($this->assigneeTypes() as $type)
                    <flux:radio :value="$type->value" :label="$type->label()" />
                @endforeach
            </flux:radio.group>

            @if ($assignee_type === App\Enums\Assignee::Staff->value)
                <flux:select wire:model="assigned_user_id" :label="__('Staff member')">
                    <flux:select.option value="">{{ __('Choose someone') }}</flux:select.option>
                    @foreach ($this->staffOptions as $staffMember)
                        <flux:select.option :value="$staffMember->id">{{ $staffMember->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @elseif ($assignee_type === App\Enums\Assignee::Vendor->value)
                <flux:select wire:model="assigned_vendor_id" :label="__('Vendor')">
                    <flux:select.option value="">{{ __('Choose a vendor') }}</flux:select.option>
                    @foreach ($this->vendorOptions as $vendor)
                        <flux:select.option :value="$vendor->id">{{ $vendor->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:input wire:model="due_on" :label="__('Due date (optional)')" type="date" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>

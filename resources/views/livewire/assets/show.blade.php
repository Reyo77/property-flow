<section class="w-full max-w-3xl space-y-8">
    <div>
        <flux:heading size="xl" level="1">{{ $asset->name }}</flux:heading>
        <flux:subheading>{{ $asset->category->label() }}{{ $asset->location ? ' · '.$asset->location : '' }}</flux:subheading>
    </div>

    @if ($asset->notes)
        <flux:text>{{ $asset->notes }}</flux:text>
    @endif

    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">{{ __('Maintenance schedules') }}</flux:heading>
            @can('update', $asset)
                <div class="flex gap-2">
                    <flux:button size="sm" icon="play" wire:click="generateNow">{{ __('Generate due now') }}</flux:button>
                    <flux:button size="sm" variant="primary" icon="plus" wire:click="createSchedule">{{ __('Add schedule') }}</flux:button>
                </div>
            @endcan
        </div>

        @if ($this->schedules->isEmpty())
            <flux:text>{{ __('No maintenance schedules yet.') }}</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Title') }}</flux:table.column>
                    <flux:table.column>{{ __('Every') }}</flux:table.column>
                    <flux:table.column>{{ __('Next due') }}</flux:table.column>
                    <flux:table.column>{{ __('Assigned to') }}</flux:table.column>
                    <flux:table.column>{{ __('Active') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->schedules as $schedule)
                        <flux:table.row :key="'schedule-'.$schedule->id">
                            <flux:table.cell variant="strong">{{ $schedule->title }}</flux:table.cell>
                            <flux:table.cell>{{ __(':days days', ['days' => $schedule->interval_days]) }}</flux:table.cell>
                            <flux:table.cell>{{ $schedule->next_due_on->toFormattedDateString() }}</flux:table.cell>
                            <flux:table.cell>{{ $schedule->assignedUser?->name ?? $schedule->assignedVendor?->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                @can('update', $asset)
                                    <flux:switch wire:click="toggleActive({{ $schedule->id }})" :checked="$schedule->active" />
                                @else
                                    {{ $schedule->active ? __('Yes') : __('No') }}
                                @endcan
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                @can('update', $asset)
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" :aria-label="__('Actions')" />
                                        <flux:menu>
                                            <flux:menu.item icon="pencil-square" wire:click="editSchedule({{ $schedule->id }})">{{ __('Edit') }}</flux:menu.item>
                                            <flux:menu.item icon="trash" variant="danger" wire:click="deleteSchedule({{ $schedule->id }})" wire:confirm="{{ __('Delete this schedule?') }}">{{ __('Delete') }}</flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                @endcan
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </div>

    @if ($this->recentWorkOrders->isNotEmpty())
        <div class="space-y-3">
            <flux:heading size="lg">{{ __('Recent work orders') }}</flux:heading>
            <ul class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                @foreach ($this->recentWorkOrders as $workOrder)
                    <li class="flex items-center justify-between px-4 py-2" wire:key="wo-{{ $workOrder->id }}">
                        <flux:text>{{ $workOrder->title }}</flux:text>
                        <flux:badge size="sm" :color="$workOrder->status->isFinal() ? 'zinc' : 'blue'">{{ $workOrder->status->label() }}</flux:badge>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <flux:modal name="schedule-form" class="w-full max-w-lg">
        <form wire:submit="saveSchedule" class="space-y-5">
            <flux:heading size="lg">{{ $editingScheduleId ? __('Edit schedule') : __('Add schedule') }}</flux:heading>

            <flux:input wire:model="title" :label="__('Title')" :placeholder="__('e.g. Quarterly inspection')" required />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="interval_days" :label="__('Repeat every (days)')" type="number" min="1" required />
                <flux:input wire:model="next_due_on" :label="__('Next due')" type="date" required />
            </div>

            <flux:radio.group wire:model.live="assignee_type" :label="__('Assign to (optional)')" variant="segmented">
                <flux:radio value="" :label="__('Nobody yet')" />
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

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>

<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Tasks') }}</flux:heading>
            <flux:subheading>{{ $community->name }}</flux:subheading>
        </div>

        @can('create', [App\Models\Task::class, $community])
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('New task') }}</flux:button>
        @endcan
    </div>

    <flux:radio.group wire:model.live="statusFilter" variant="segmented">
        <flux:radio value="open" :label="__('Open')" />
        <flux:radio value="done" :label="__('Done')" />
        <flux:radio value="" :label="__('All')" />
    </flux:radio.group>

    @if ($this->tasks->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <flux:heading>{{ __('No tasks here') }}</flux:heading>
        </div>
    @else
        <ul class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
            @foreach ($this->tasks as $task)
                <li class="flex items-start justify-between gap-3 px-4 py-3" wire:key="task-{{ $task->id }}">
                    <div class="flex items-start gap-3">
                        @can('update', $task)
                            <flux:checkbox wire:click="toggleDone({{ $task->id }})" :checked="$task->status === App\Enums\TaskStatus::Done" />
                        @endcan
                        <div>
                            <flux:text class="{{ $task->status === App\Enums\TaskStatus::Done ? 'line-through text-zinc-400' : 'text-zinc-800 dark:text-zinc-100' }} font-medium">
                                {{ $task->title }}
                            </flux:text>
                            @if ($task->description)
                                <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ $task->description }}</div>
                            @endif
                            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                                @if ($task->due_on)
                                    <span>{{ __('Due :date', ['date' => $task->due_on->toFormattedDateString()]) }}</span>
                                @endif
                                @if ($task->isOverdue())
                                    <flux:badge size="sm" color="red">{{ __('Overdue') }}</flux:badge>
                                @endif
                                @if ($task->assignedTo)
                                    <span>{{ __('Assigned to :name', ['name' => $task->assignedTo->name]) }}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    @can('update', $task)
                        <flux:dropdown position="bottom" align="end">
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" :aria-label="__('Actions')" />
                            <flux:menu>
                                <flux:menu.item icon="pencil-square" wire:click="edit({{ $task->id }})">{{ __('Edit') }}</flux:menu.item>
                                <flux:menu.item icon="trash" variant="danger" wire:click="delete({{ $task->id }})" wire:confirm="{{ __('Delete :title?', ['title' => $task->title]) }}">{{ __('Delete') }}</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    @endcan
                </li>
            @endforeach
        </ul>
    @endif

    <flux:modal name="task-form" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingTaskId ? __('Edit task') : __('New task') }}</flux:heading>

            <flux:input wire:model="title" :label="__('Title')" required />
            <flux:textarea wire:model="description" :label="__('Description')" rows="2" />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="due_on" :label="__('Due date')" type="date" />
                <flux:select wire:model="assigned_to_id" :label="__('Assign to')">
                    <flux:select.option value="">{{ __('Unassigned') }}</flux:select.option>
                    @foreach ($this->teamOptions as $member)
                        <flux:select.option :value="$member->id">{{ $member->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>

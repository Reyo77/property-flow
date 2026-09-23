<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Announcements') }}</flux:heading>
            <flux:subheading>{{ $community->name }}</flux:subheading>
        </div>

        @if ($this->canManage())
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('New announcement') }}</flux:button>
        @endif
    </div>

    @if ($this->announcements->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <flux:heading>{{ __('No announcements yet') }}</flux:heading>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($this->announcements as $announcement)
                <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700" wire:key="announcement-{{ $announcement->id }}">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                @if ($announcement->pinned)
                                    <flux:icon name="bookmark" variant="solid" class="size-4 text-amber-500" />
                                @endif
                                <flux:heading size="lg">{{ $announcement->title }}</flux:heading>
                            </div>
                            <flux:text class="mt-1 text-sm">
                                {{ $this->statusLabel($announcement) }}
                                @if ($announcement->createdBy)
                                    · {{ __('by :name', ['name' => $announcement->createdBy->name]) }}
                                @endif
                                @unless ($announcement->audience_type->value === 'community')
                                    · {{ $announcement->audience_type->label() }}
                                    @if ($announcement->residency_type)
                                        ({{ $announcement->residency_type->label() }})
                                    @endif
                                @endunless
                            </flux:text>
                        </div>

                        @can('update', $announcement)
                            <flux:dropdown position="bottom" align="end">
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" :aria-label="__('Actions')" />
                                <flux:menu>
                                    <flux:menu.item icon="pencil-square" wire:click="edit({{ $announcement->id }})">{{ __('Edit') }}</flux:menu.item>
                                    <flux:menu.item icon="bookmark" wire:click="togglePin({{ $announcement->id }})">
                                        {{ $announcement->pinned ? __('Unpin') : __('Pin') }}
                                    </flux:menu.item>
                                    @unless ($announcement->isPublished())
                                        <flux:menu.item icon="paper-airplane" wire:click="publishNow({{ $announcement->id }})">{{ __('Publish now') }}</flux:menu.item>
                                    @endunless
                                    <flux:menu.item icon="trash" variant="danger" wire:click="delete({{ $announcement->id }})" wire:confirm="{{ __('Delete :title?', ['title' => $announcement->title]) }}">{{ __('Delete') }}</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        @endcan
                    </div>

                    <flux:text class="mt-3 whitespace-pre-line">{{ $announcement->body }}</flux:text>
                </div>
            @endforeach
        </div>
    @endif

    <flux:modal name="announcement-form" class="w-full max-w-2xl">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingAnnouncementId ? __('Edit announcement') : __('New announcement') }}</flux:heading>

            <flux:input wire:model="title" :label="__('Title')" required />
            <flux:textarea wire:model="body" :label="__('Message')" rows="5" required />

            <flux:select wire:model.live="audience_type" :label="__('Audience')">
                @foreach (App\Enums\AnnouncementAudience::cases() as $option)
                    <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($audience_type === App\Enums\AnnouncementAudience::ResidencyType->value)
                <flux:select wire:model="residency_type" :label="__('Residency type')">
                    <flux:select.option value="">{{ __('Choose a type') }}</flux:select.option>
                    @foreach (App\Enums\ResidencyType::cases() as $type)
                        <flux:select.option :value="$type->value">{{ $type->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            @elseif ($audience_type === App\Enums\AnnouncementAudience::Buildings->value)
                <flux:checkbox.group wire:model="building_ids" :label="__('Buildings')">
                    @foreach ($this->availableBuildings as $building)
                        <flux:checkbox :value="$building->id" :label="$building->name" />
                    @endforeach
                </flux:checkbox.group>
                <flux:error name="building_ids" />
            @elseif ($audience_type === App\Enums\AnnouncementAudience::Units->value)
                <div class="space-y-2">
                    <flux:input wire:model.live.debounce.300ms="unitSearch" icon="magnifying-glass" :placeholder="__('Search unit number')" />
                    <div class="max-h-48 space-y-1 overflow-y-auto rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        <flux:checkbox.group wire:model="unit_ids">
                            @foreach ($this->filteredUnits as $unit)
                                <flux:checkbox :value="$unit->id" :label="($unit->building ? $unit->building->name.' · ' : '').$unit->number" />
                            @endforeach
                        </flux:checkbox.group>
                    </div>
                    @if (count($unit_ids) > 0)
                        <flux:text class="text-sm">{{ __(':count unit(s) selected', ['count' => count($unit_ids)]) }}</flux:text>
                    @endif
                </div>
                <flux:error name="unit_ids" />
            @endif

            @unless ($editingIsPublished)
                <flux:checkbox wire:model.live="scheduleForLater" :label="__('Schedule for later')" />

                @if ($scheduleForLater)
                    <flux:input wire:model="publish_at" :label="__('Publish at')" type="datetime-local" required />
                @endif
            @endunless

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">
                    {{ $scheduleForLater ? __('Schedule') : __('Publish') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</section>

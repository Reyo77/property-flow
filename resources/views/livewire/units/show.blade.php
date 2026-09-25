<section class="w-full space-y-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Unit :number', ['number' => $unit->number]) }}</flux:heading>
            <flux:subheading>{{ collect([$unit->building?->name, $community->name])->filter()->implode(' · ') }}</flux:subheading>
        </div>

        <div class="flex gap-2">
            @can('viewLedger', $unit)
                <flux:button icon="banknotes" :href="route('communities.units.account', [$community, $unit])" wire:navigate>{{ __('Account') }}</flux:button>
            @endcan
            @can('manageResidents', $unit)
                <flux:button variant="primary" icon="user-plus" wire:click="openAddResident">{{ __('Add resident') }}</flux:button>
            @endcan
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
        @foreach ([
            __('Floor') => $unit->floor,
            __('Area') => $unit->area === null ? null : $unit->area.' '.$community->area_unit->label(),
            __('Unit factor') => $unit->unit_factor === null ? null : rtrim(rtrim($unit->unit_factor, '0'), '.').'%',
            __('Parking') => $unit->parking,
            __('Locker') => $unit->locker,
        ] as $label => $value)
            <div>
                <flux:text>{{ $label }}</flux:text>
                <flux:heading>{{ $value ?? '—' }}</flux:heading>
            </div>
        @endforeach
    </div>

    <div class="space-y-3">
        <flux:heading size="lg">{{ __('Current residents') }}</flux:heading>

        @if ($this->currentResidencies->isEmpty())
            <flux:text data-test="vacant">{{ __('Nobody is recorded as living in or owning this unit.') }}</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Type') }}</flux:table.column>
                    <flux:table.column>{{ __('Since') }}</flux:table.column>
                    <flux:table.column>{{ __('Contact') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->currentResidencies as $residency)
                        <flux:table.row :key="$residency->id">
                            <flux:table.cell variant="strong">
                                <flux:link :href="route('communities.residents.show', [$community, $residency->resident])" wire:navigate>{{ $residency->resident->name }}</flux:link>
                                @if ($residency->is_primary)
                                    <flux:badge size="sm" color="blue" class="ms-1">{{ __('Primary') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $residency->type->label() }}</flux:table.cell>
                            <flux:table.cell>{{ $residency->moved_in_on?->toFormattedDateString() ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                <div>{{ $residency->resident->email }}</div>
                                <div class="text-xs">{{ $residency->resident->phone }}</div>
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                @can('manageResidents', $unit)
                                    <flux:button size="sm" variant="ghost" icon="arrow-right-start-on-rectangle" wire:click="openMoveOut({{ $residency->id }})">{{ __('Move out') }}</flux:button>
                                @endcan
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </div>

    @if ($this->pastResidencies->isNotEmpty())
        <div class="space-y-3">
            <flux:heading size="lg">{{ __('Past residents') }}</flux:heading>
            <ul class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                @foreach ($this->pastResidencies as $residency)
                    <li class="flex flex-wrap justify-between gap-2 px-3 py-2" wire:key="past-{{ $residency->id }}">
                        <flux:link :href="route('communities.residents.show', [$community, $residency->resident])" wire:navigate>{{ $residency->resident->name }}</flux:link>
                        <flux:text>{{ $residency->type->label() }} · {{ $residency->moved_in_on?->toFormattedDateString() ?? '?' }} → {{ $residency->moved_out_on?->toFormattedDateString() }}</flux:text>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="space-y-3">
        <flux:heading size="lg">{{ __('History') }}</flux:heading>
        @if ($this->history->isEmpty())
            <flux:text>{{ __('No changes recorded yet.') }}</flux:text>
        @else
            <ul class="space-y-2">
                @foreach ($this->history as $activity)
                    <li class="flex flex-wrap gap-x-2 text-sm" wire:key="activity-{{ $activity->id }}">
                        <span class="text-zinc-500 dark:text-zinc-400">{{ $activity->created_at?->toDayDateTimeString() }}</span>
                        <span class="text-zinc-800 dark:text-zinc-200">
                            {{ class_basename($activity->subject_type) === 'Unit' ? __('Unit') : __('Residency') }} {{ $activity->event ?? $activity->description }}
                        </span>
                        @if ($activity->causer)
                            <span class="text-zinc-500 dark:text-zinc-400">{{ __('by :name', ['name' => $activity->causer->getAttribute('name')]) }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <flux:modal name="add-resident" class="w-full max-w-lg">
        <form wire:submit="addResident" class="space-y-5">
            <flux:heading size="lg">{{ __('Add a resident to unit :number', ['number' => $unit->number]) }}</flux:heading>

            <flux:radio.group wire:model.live="residentSource" variant="segmented">
                <flux:radio value="new" :label="__('New person')" />
                <flux:radio value="existing" :label="__('Existing resident')" />
            </flux:radio.group>

            @if ($residentSource === 'existing')
                <flux:input wire:model.live.debounce.300ms="residentSearch" icon="magnifying-glass" :placeholder="__('Search by name or email')" />
                @if ($this->residentMatches->isNotEmpty())
                    <flux:radio.group wire:model="existingResidentId">
                        @foreach ($this->residentMatches as $match)
                            <flux:radio :value="$match->id" :label="$match->name" :description="$match->email" />
                        @endforeach
                    </flux:radio.group>
                @elseif (mb_strlen($residentSearch) >= 2)
                    <flux:text>{{ __('No residents match.') }}</flux:text>
                @endif
                <flux:error name="existingResidentId" />
            @else
                <flux:input wire:model="name" :label="__('Name')" required />
                <flux:input wire:model="email" :label="__('Email')" type="email" :description="__('Needed to invite them to the resident portal.')" />
                <flux:input wire:model="phone" :label="__('Phone')" />
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="type" :label="__('Type')">
                    @foreach (App\Enums\ResidencyType::cases() as $residencyType)
                        <flux:select.option :value="$residencyType->value">{{ $residencyType->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="movedInOn" :label="__('Move-in date')" type="date" />
            </div>

            <flux:checkbox wire:model="isPrimary" :label="__('Primary contact for this unit')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Add resident') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="move-out" class="w-full max-w-md">
        <form wire:submit="moveOut" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Record a move-out') }}</flux:heading>
                <flux:text class="mt-1">{{ __('From this date they no longer count as living here or have portal access to this unit.') }}</flux:text>
            </div>
            <flux:input wire:model="movedOutOn" :label="__('Move-out date')" type="date" required />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Record move-out') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>

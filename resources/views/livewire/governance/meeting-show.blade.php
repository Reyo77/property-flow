<section class="w-full max-w-4xl space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ $meeting->title }}</flux:heading>
            <flux:subheading>{{ $meeting->kind->label() }} · {{ App\Support\Governance\LocalTime::display($meeting->starts_at, $community) }}{{ $meeting->location ? ' · '.$meeting->location : '' }}</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            @if ($meeting->isClosed())
                <flux:badge>{{ __('Closed') }}</flux:badge>
            @elseif ($this->canManage())
                @if ($meeting->kind->isOwnersMeeting())
                    <flux:button size="sm" icon="plus" :href="route('communities.ballots.create', [$community, 'meeting' => $meeting->id])" wire:navigate>{{ __('Add ballot') }}</flux:button>
                    <flux:button size="sm" variant="primary" wire:click="close" wire:confirm="{{ __('Close the meeting? Attendance becomes final.') }}">{{ __('Close meeting') }}</flux:button>
                @else
                    <flux:button size="sm" variant="primary" wire:click="close" wire:confirm="{{ __('Close the meeting?') }}">{{ __('Close meeting') }}</flux:button>
                @endif
            @endif
        </div>
    </div>

    @if ($meeting->description)
        <flux:text class="whitespace-pre-line">{{ $meeting->description }}</flux:text>
    @endif

    <div @class(['grid gap-4', 'sm:grid-cols-2' => $meeting->kind->isOwnersMeeting()])>
        <div class="space-y-2 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:heading>{{ __('Agenda') }}</flux:heading>
            <ol class="list-decimal space-y-1 ps-5 text-sm">
                @foreach ($meeting->agendaItems as $item)
                    <li>{{ $item->title }}</li>
                @endforeach
            </ol>
        </div>
        @if ($meeting->kind->isOwnersMeeting())
        <div @class(['space-y-1 rounded-xl border p-5', 'border-green-300 dark:border-green-700' => $this->quorum['met'], 'border-zinc-200 dark:border-zinc-700' => ! $this->quorum['met']]) data-test="quorum">
            <flux:heading>{{ __('Quorum') }}</flux:heading>
            <flux:heading size="xl">{{ $this->quorum['percent'] }}%</flux:heading>
            <flux:text size="sm">
                {{ __(':present of :eligible owner units represented · :required% needed (:weighting)', ['present' => $this->quorum['represented_units'], 'eligible' => $this->quorum['eligible_units'], 'required' => $this->quorum['required_percent'], 'weighting' => $meeting->weighting->label()]) }}
            </flux:text>
            <flux:badge :color="$this->quorum['met'] ? 'green' : 'amber'">{{ $this->quorum['met'] ? __('Quorum present') : __('No quorum yet') }}</flux:badge>
        </div>
        @endif
    </div>

    @if ($this->ballots->isNotEmpty())
        <div class="space-y-2">
            <flux:heading size="lg">{{ __('Ballots') }}</flux:heading>
            @foreach ($this->ballots as $ballot)
                @php($status = $ballot->status())
                <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    <flux:link :href="route('communities.ballots.show', [$community, $ballot])" wire:navigate>{{ $ballot->title }}</flux:link>
                    <flux:badge size="sm" :color="$status->color()">{{ $status->label() }}</flux:badge>
                </div>
            @endforeach
        </div>
    @endif

    @if ($this->canManage())
        @if ($meeting->kind->isOwnersMeeting())
        <div class="space-y-3">
            <flux:heading size="lg">{{ __('Attendance') }}</flux:heading>
            @unless ($meeting->isClosed())
                <form wire:submit="checkIn" class="flex flex-wrap items-end gap-2">
                    <div class="min-w-48 flex-1">
                        <flux:select wire:model="attendance_unit_id" :label="__('Unit')">
                            <flux:select.option value="">{{ __('Choose an owner unit') }}</flux:select.option>
                            @foreach ($this->unitsToCheckIn as $unit)
                                <flux:select.option :value="$unit->id">{{ $unit->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <flux:select wire:model="attendance_mode" :label="__('Represented')" class="max-w-40">
                        @foreach (App\Enums\AttendanceMode::cases() as $option)
                            <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="attendee_name" :label="__('Name (optional)')" class="max-w-48" />
                    <flux:button type="submit" variant="primary">{{ __('Check in') }}</flux:button>
                </form>
                <flux:error name="attendance_unit_id" />
            @endunless
            @if ($this->attendances->isNotEmpty())
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Unit') }}</flux:table.column>
                        <flux:table.column>{{ __('Represented') }}</flux:table.column>
                        <flux:table.column>{{ __('Name') }}</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->attendances as $attendance)
                            <flux:table.row :key="$attendance->id">
                                <flux:table.cell>{{ $attendance->unit->label() }}</flux:table.cell>
                                <flux:table.cell>{{ $attendance->represented_by->label() }}</flux:table.cell>
                                <flux:table.cell>{{ $attendance->attendee_name }}</flux:table.cell>
                                <flux:table.cell align="end">
                                    @unless ($meeting->isClosed())
                                        <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="removeAttendance({{ $attendance->id }})" :aria-label="__('Remove')" />
                                    @endunless
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </div>
        @endif

        <div class="space-y-3">
            <flux:heading size="lg">{{ __('Minutes') }}</flux:heading>
            <flux:textarea wire:model="minutes" rows="10" :placeholder="__('Record decisions, motions and votes…')" />
            <flux:error name="minutes" />
            <div class="flex flex-wrap items-center justify-end gap-2">
                @if ($meeting->hasPublishedMinutes())
                    <flux:text size="sm">{{ __('Published :date', ['date' => App\Support\Governance\LocalTime::display($meeting->minutes_published_at, $community)]) }}</flux:text>
                    <flux:button wire:click="saveMinutes(false)">{{ __('Unpublish') }}</flux:button>
                @else
                    <flux:button wire:click="saveMinutes(false)">{{ __('Save draft') }}</flux:button>
                @endif
                <flux:button variant="primary" wire:click="saveMinutes(true)">{{ $meeting->hasPublishedMinutes() ? __('Save and keep published') : __('Publish to owners') }}</flux:button>
            </div>
        </div>
    @else
        @can('viewMinutes', $meeting)
            <div class="space-y-2 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:heading size="lg">{{ __('Minutes') }}</flux:heading>
                <flux:text class="whitespace-pre-line">{{ $meeting->minutes }}</flux:text>
            </div>
        @endcan
    @endif
</section>

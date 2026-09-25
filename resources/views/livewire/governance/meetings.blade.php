<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Meetings') }}</flux:heading>
            <flux:subheading>{{ $community->name }}</flux:subheading>
        </div>
        @if ($this->canCreate())
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Schedule meeting') }}</flux:button>
        @endif
    </div>

    @if ($this->meetings->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <flux:heading>{{ __('No meetings yet') }}</flux:heading>
        </div>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Meeting') }}</flux:table.column>
                <flux:table.column>{{ __('When') }}</flux:table.column>
                <flux:table.column>{{ __('Where') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->meetings as $meeting)
                    <flux:table.row :key="$meeting->id">
                        <flux:table.cell variant="strong">
                            <flux:link :href="route('communities.meetings.show', [$community, $meeting])" wire:navigate>{{ $meeting->title }}</flux:link>
                            <flux:text size="sm">{{ $meeting->kind->label() }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>{{ App\Support\Governance\LocalTime::display($meeting->starts_at, $community) }}</flux:table.cell>
                        <flux:table.cell>{{ $meeting->location }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($meeting->isClosed())
                                <flux:badge size="sm">{{ $meeting->hasPublishedMinutes() ? __('Minutes published') : __('Held') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="blue">{{ __('Scheduled') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="meeting-form" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ __('Schedule meeting') }}</flux:heading>
            <flux:input wire:model="title" :label="__('Title')" :placeholder="__('e.g. 2026 Annual General Meeting')" required />
            <div class="grid grid-cols-2 gap-4">
                <flux:select wire:model="kind" :label="__('Type')">
                    @foreach (App\Enums\MeetingKind::cases() as $option)
                        <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="starts_at" type="datetime-local" :label="__('Starts')" required />
            </div>
            <flux:input wire:model="location" :label="__('Location or link')" />
            <div class="grid grid-cols-2 gap-4">
                <flux:select wire:model="weighting" :label="__('Quorum counted')">
                    @foreach (App\Enums\VotingWeighting::cases() as $option)
                        <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="quorum_percent" type="number" min="0" max="100" :label="__('Quorum %')" required />
            </div>
            <flux:textarea wire:model="agenda" :label="__('Agenda (one item per line)')" rows="6" />
            <flux:textarea wire:model="description" :label="__('Notice to owners (optional)')" rows="2" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Schedule') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>

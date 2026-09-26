<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Incident reports') }}</flux:heading>
            <flux:subheading>{{ $community->name }}</flux:subheading>
        </div>

        @can('create', [App\Models\IncidentReport::class, $community])
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Report incident') }}</flux:button>
        @endcan
    </div>

    @if ($this->incidentReports->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <flux:heading>{{ __('No incidents reported') }}</flux:heading>
        </div>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Title') }}</flux:table.column>
                <flux:table.column>{{ __('Location') }}</flux:table.column>
                <flux:table.column>{{ __('Occurred') }}</flux:table.column>
                <flux:table.column>{{ __('Severity') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->incidentReports as $incident)
                    <flux:table.row :key="$incident->id">
                        <flux:table.cell variant="strong">
                            <flux:link :href="route('communities.incident-reports.show', [$community, $incident])" wire:navigate>{{ $incident->title }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $incident->location }}</flux:table.cell>
                        <flux:table.cell>{{ $incident->occurred_at->format('M j, g:ia') }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$incident->severity->color()">{{ $incident->severity->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$incident->isResolved() ? 'zinc' : 'blue'">
                                {{ $incident->isResolved() ? __('Resolved') : __('Open') }}
                            </flux:badge>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="incident-form" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ __('Report incident') }}</flux:heading>

            <flux:input wire:model="title" :label="__('Title')" required />
            <flux:textarea wire:model="description" :label="__('Description')" rows="4" required />
            <flux:input wire:model="location" :label="__('Location (optional)')" />

            <div class="grid grid-cols-2 gap-4">
                <flux:select wire:model="severity" :label="__('Severity')">
                    <flux:select.option value="">{{ __('Choose severity') }}</flux:select.option>
                    @foreach ($this->severities() as $option)
                        <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="occurred_at" :label="__('Occurred at')" type="datetime-local" required />
            </div>

            <flux:select wire:model="unit_id" :label="__('Unit (optional)')">
                <flux:select.option value="">{{ __('Not unit-specific') }}</flux:select.option>
                @foreach ($this->units as $unit)
                    <flux:select.option :value="$unit->id">{{ ($unit->building?->name.' · ') ?: '' }}{{ $unit->number }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input type="file" wire:model="photos" multiple :label="__('Photos (optional)')" :description="__('Up to 6 images, 8 MB each.')" accept="image/*" />
            <flux:error name="photos.*" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="photos,save">{{ __('Submit') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>

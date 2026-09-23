<section class="w-full max-w-3xl space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Import units') }}</flux:heading>
        <flux:subheading>{{ $community->name }}</flux:subheading>
    </div>

    <div class="space-y-2">
        <flux:text>
            {{ __('Upload a CSV or Excel (.xlsx) file with a heading row. Columns:') }}
            <code class="text-sm">{{ implode(', ', App\Actions\Units\ImportUnits::COLUMNS) }}</code>.
        </flux:text>
        <flux:text>
            {{ __('Only "number" is required. Buildings that do not exist yet are created. Nothing is imported unless every row is valid.') }}
        </flux:text>
        <flux:button size="sm" icon="arrow-down-tray" wire:click="downloadTemplate">{{ __('Download template') }}</flux:button>
    </div>

    <form wire:submit="import" class="space-y-4">
        <flux:input type="file" wire:model="file" :label="__('Spreadsheet')" accept=".csv,.xlsx,text/csv" />

        <div class="flex items-center gap-3">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="file,import">{{ __('Import') }}</flux:button>
            <flux:button :href="route('communities.units.index', $community)" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
        </div>
    </form>

    @if ($rowErrors !== [])
        <flux:callout variant="danger" icon="x-circle" data-test="import-errors">
            <flux:callout.heading>{{ __('Nothing was imported. Fix these rows and try again.') }}</flux:callout.heading>
            <flux:callout.text>
                <ul class="mt-2 space-y-1">
                    @foreach ($rowErrors as $row => $messages)
                        <li><strong>{{ __('Row :row', ['row' => $row]) }}:</strong> {{ implode(' ', $messages) }}</li>
                    @endforeach
                </ul>
            </flux:callout.text>
        </flux:callout>
    @endif
</section>

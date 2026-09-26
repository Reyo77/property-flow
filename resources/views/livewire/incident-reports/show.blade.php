<section class="w-full max-w-3xl space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <flux:heading size="xl" level="1">{{ $incidentReport->title }}</flux:heading>
                <flux:badge :color="$incidentReport->severity->color()">{{ $incidentReport->severity->label() }}</flux:badge>
                <flux:badge :color="$incidentReport->isResolved() ? 'zinc' : 'blue'">
                    {{ $incidentReport->isResolved() ? __('Resolved') : __('Open') }}
                </flux:badge>
            </div>
            <flux:subheading>
                {{ App\Support\LocalTime::local($incidentReport->occurred_at, $community)->format('M j, Y g:ia') }}
                @if ($incidentReport->location)
                    · {{ $incidentReport->location }}
                @endif
                @if ($incidentReport->unit)
                    · {{ ($incidentReport->unit->building?->name.' · ') ?: '' }}{{ __('Unit :number', ['number' => $incidentReport->unit->number]) }}
                @endif
            </flux:subheading>
        </div>
    </div>

    <flux:text class="whitespace-pre-line">{{ $incidentReport->description }}</flux:text>

    @if ($incidentReport->attachments->isNotEmpty())
        <div class="space-y-2">
            <flux:heading size="lg">{{ __('Photos') }}</flux:heading>
            <div class="flex flex-wrap gap-3">
                @foreach ($incidentReport->attachments as $attachment)
                    <a href="{{ route('communities.attachments.download', [$community, $attachment]) }}" target="_blank" class="block size-24 overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <img src="{{ route('communities.attachments.download', [$community, $attachment]) }}" alt="{{ $attachment->original_filename }}" class="size-full object-cover" />
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    @if ($incidentReport->isResolved())
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Resolution') }}</flux:heading>
            <flux:text class="mt-1 text-sm">{{ __('Resolved :date', ['date' => $incidentReport->resolved_at->format('M j, Y g:ia')]) }}</flux:text>
            @if ($incidentReport->resolution_notes)
                <flux:text class="mt-2 whitespace-pre-line">{{ $incidentReport->resolution_notes }}</flux:text>
            @endif
        </div>
    @elseif (auth()->user()->can('update', $incidentReport))
        <form wire:submit="resolve" class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Mark resolved') }}</flux:heading>
            <flux:textarea wire:model="resolution_notes" :label="__('Resolution notes (optional)')" rows="3" />
            <flux:button type="submit" variant="primary">{{ __('Mark resolved') }}</flux:button>
        </form>
    @endif
</section>

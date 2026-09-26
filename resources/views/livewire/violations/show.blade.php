<section class="w-full max-w-4xl space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ $violation->rule->title }}</flux:heading>
            <flux:subheading>{{ __('Unit :unit', ['unit' => $violation->unit->label()]) }} · {{ App\Support\LocalTime::display($violation->observed_at, $community) }}</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:badge :color="$violation->status->color()">{{ $violation->status->label() }}</flux:badge>
            @if ($this->canManage() && $violation->isOpen())
                <flux:button size="sm" wire:click="escalate" wire:confirm="{{ __('Issue the next notice now, without waiting for the cure period?') }}">{{ __('Escalate now') }}</flux:button>
                <flux:modal.trigger name="close-violation"><flux:button size="sm" variant="primary">{{ __('Close…') }}</flux:button></flux:modal.trigger>
            @endif
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text size="sm">{{ __('Rule') }}</flux:text>
            <flux:text variant="strong">{{ $violation->rule->reference ?? $violation->rule->title }}</flux:text>
        </div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text size="sm">{{ __('Stage') }}</flux:text>
            <flux:text variant="strong">{{ $violation->stage?->label() }}{{ $violation->fines_issued ? ' · '.trans_choice(':count fine|:count fines', $violation->fines_issued) : '' }}</flux:text>
        </div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text size="sm">{{ __('Next step') }}</flux:text>
            <flux:text variant="strong">
                @if (! $violation->isOpen())
                    {{ $violation->resolution_notes ?? '—' }}
                @elseif ($violation->next_action_on)
                    {{ __('Escalates :date if not fixed', ['date' => $violation->next_action_on->toFormattedDateString()]) }}
                @else
                    {{ __('Board review') }}
                @endif
            </flux:text>
        </div>
    </div>

    <flux:text class="whitespace-pre-line">{{ $violation->description }}{{ $violation->location ? ' ('.$violation->location.')' : '' }}</flux:text>

    @if ($photos->isNotEmpty())
        <div class="flex flex-wrap gap-3">
            @foreach ($photos as $photo)
                <a href="{{ route('communities.attachments.download', [$community, $photo]) }}" target="_blank" class="block size-28 overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <img src="{{ route('communities.attachments.download', [$community, $photo]) }}" alt="{{ $photo->original_filename }}" class="size-full object-cover" />
                </a>
            @endforeach
        </div>
    @endif

    <div class="space-y-3">
        <flux:heading size="lg">{{ __('Notices') }}</flux:heading>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Issued') }}</flux:table.column>
                <flux:table.column>{{ __('Notice') }}</flux:table.column>
                <flux:table.column>{{ __('Fix by') }}</flux:table.column>
                <flux:table.column>{{ __('Fine') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($notices as $notice)
                    <flux:table.row :key="$notice->id">
                        <flux:table.cell>{{ $notice->issued_on->toFormattedDateString() }}</flux:table.cell>
                        <flux:table.cell variant="strong">{{ $notice->stage->label() }}</flux:table.cell>
                        <flux:table.cell>{{ $notice->cure_by?->toFormattedDateString() }}</flux:table.cell>
                        <flux:table.cell>{{ $notice->invoice ? $notice->invoice->displayNumber().' · '.App\Support\Finance\Money::of($notice->invoice->total_cents)->format() : '' }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button size="sm" variant="ghost" icon="arrow-down-tray" :href="route('communities.violations.notices.letter', [$community, $violation, $notice])">{{ __('Letter') }}</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="close-violation" class="w-full max-w-md">
        <div class="space-y-5">
            <flux:heading size="lg">{{ __('Close violation') }}</flux:heading>
            <flux:textarea wire:model="resolution_notes" :label="__('Notes (optional)')" rows="2" />
            <flux:text size="sm">{{ __('Fines already issued stay on the unit\'s account; void the invoice in Finance to waive one.') }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:button wire:click="close('dismissed')">{{ __('Dismiss') }}</flux:button>
                <flux:button variant="primary" wire:click="close('resolved')">{{ __('Mark resolved') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</section>

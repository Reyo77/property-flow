<section class="w-full max-w-4xl space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ $architecturalRequest->title }}</flux:heading>
            <flux:subheading>{{ __('Unit :unit', ['unit' => $architecturalRequest->unit->label()]) }} · {{ __('submitted :date by :name', ['date' => $architecturalRequest->created_at?->toFormattedDateString(), 'name' => $architecturalRequest->submittedBy?->name]) }}</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:badge :color="$architecturalRequest->status->color()">{{ $architecturalRequest->status->label() }}</flux:badge>
            @can('withdraw', $architecturalRequest)
                <flux:button size="sm" wire:click="withdraw" wire:confirm="{{ __('Withdraw this request?') }}">{{ __('Withdraw') }}</flux:button>
            @endcan
            @if ($architecturalRequest->status->isDecided())
                <flux:button size="sm" icon="arrow-down-tray" :href="route('communities.architectural-requests.letter', [$community, $architecturalRequest])">{{ __('Decision letter') }}</flux:button>
            @endif
        </div>
    </div>

    <flux:text class="whitespace-pre-line">{{ $architecturalRequest->description }}</flux:text>

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text size="sm">{{ __('Contractor') }}</flux:text>
            <flux:text variant="strong">{{ $architecturalRequest->contractor ?? '—' }}</flux:text>
        </div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text size="sm">{{ __('Planned start') }}</flux:text>
            <flux:text variant="strong">{{ $architecturalRequest->planned_start_on?->toFormattedDateString() ?? '—' }}</flux:text>
        </div>
    </div>

    @if ($plans->isNotEmpty())
        <div class="space-y-2">
            <flux:heading>{{ __('Plans') }}</flux:heading>
            @foreach ($plans as $plan)
                <div><flux:link :href="route('communities.attachments.download', [$community, $plan])" target="_blank">{{ $plan->original_filename }}</flux:link></div>
            @endforeach
        </div>
    @endif

    @if ($architecturalRequest->status->isDecided())
        <div class="space-y-2 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700" data-test="decision">
            <flux:heading>{{ $architecturalRequest->status->label() }} · {{ $architecturalRequest->decided_at?->toFormattedDateString() }}</flux:heading>
            @if ($architecturalRequest->conditions)
                <flux:text variant="strong">{{ __('Conditions') }}</flux:text>
                <flux:text class="whitespace-pre-line">{{ $architecturalRequest->conditions }}</flux:text>
            @endif
            @if ($architecturalRequest->decision_notes)
                <flux:text class="whitespace-pre-line">{{ $architecturalRequest->decision_notes }}</flux:text>
            @endif
        </div>
    @endif

    @can('decide', $architecturalRequest)
        <div class="space-y-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ __('Board decision') }}</flux:heading>
                @if ($architecturalRequest->status === App\Enums\ArchitecturalRequestStatus::Submitted)
                    <flux:button size="sm" wire:click="startReview">{{ __('Mark under review') }}</flux:button>
                @endif
            </div>
            <flux:radio.group wire:model.live="decision" variant="segmented">
                <flux:radio value="approved" :label="__('Approve')" />
                <flux:radio value="approved_with_conditions" :label="__('Approve with conditions')" />
                <flux:radio value="denied" :label="__('Deny')" />
            </flux:radio.group>
            @if ($decision === 'approved_with_conditions')
                <flux:textarea wire:model="conditions" :label="__('Conditions')" rows="3" />
            @endif
            <flux:textarea wire:model="decision_notes" :label="$decision === 'denied' ? __('Reason') : __('Notes to the owner (optional)')" rows="3" />
            <flux:error name="decision" />
            <div class="flex justify-end">
                <flux:button variant="primary" wire:click="decide" wire:confirm="{{ __('Record this decision? It is final.') }}">{{ __('Record decision') }}</flux:button>
            </div>
        </div>
    @endcan
</section>

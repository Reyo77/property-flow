<section class="w-full max-w-4xl space-y-8">
    <div>
        <flux:heading size="xl" level="1">{{ $patrolRoute->name }}</flux:heading>
        <flux:subheading>{{ $community->name }}</flux:subheading>
    </div>

    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">{{ __('Checkpoints') }}</flux:heading>
        </div>

        @if ($this->checkpoints->isEmpty())
            <flux:text>{{ __('No checkpoints yet.') }}</flux:text>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 print:grid-cols-2">
                @foreach ($this->checkpoints as $checkpoint)
                    <div class="space-y-2 rounded-xl border border-zinc-200 p-4 text-center dark:border-zinc-700" wire:key="checkpoint-{{ $checkpoint->id }}">
                        <flux:heading>{{ $checkpoint->name }}</flux:heading>
                        <img src="{{ route('communities.patrol-checkpoints.qr', [$community, $checkpoint]) }}" alt="{{ __('QR code') }}" class="mx-auto size-40" />
                        <flux:text class="font-mono text-xs">{{ $checkpoint->qr_token }}</flux:text>
                        @can('update', $patrolRoute)
                            <div class="print:hidden">
                                <flux:button size="sm" variant="danger" wire:click="deleteCheckpoint({{ $checkpoint->id }})" wire:confirm="{{ __('Remove :name?', ['name' => $checkpoint->name]) }}">{{ __('Remove') }}</flux:button>
                            </div>
                        @endcan
                    </div>
                @endforeach
            </div>
        @endif

        @can('update', $patrolRoute)
            <form wire:submit="addCheckpoint" class="flex flex-wrap items-end gap-3 print:hidden">
                <flux:input wire:model="name" :label="__('New checkpoint name')" />
                <flux:button type="submit" size="sm">{{ __('Add checkpoint') }}</flux:button>
                <flux:button type="button" size="sm" variant="ghost" onclick="window.print()">{{ __('Print cards') }}</flux:button>
            </form>
        @endcan
    </div>

    <div class="space-y-4 print:hidden">
        <flux:heading size="lg">{{ __('Missed-checkpoint report') }}</flux:heading>
        <flux:input wire:model.live="reportDate" type="date" class="max-w-xs" />

        @if ($this->checkpoints->isEmpty())
            <flux:text>{{ __('Add checkpoints to see a report.') }}</flux:text>
        @else
            <div class="space-y-2">
                @foreach ($this->scanSummary as $entry)
                    <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        <flux:text class="font-medium text-zinc-800 dark:text-zinc-100">{{ $entry['checkpoint']->name }}</flux:text>
                        @if ($entry['last_scan_at'])
                            <flux:badge color="green">{{ __('Scanned :time', ['time' => $entry['last_scan_at']->format('g:ia')]) }}</flux:badge>
                        @else
                            <flux:badge color="red">{{ __('Missed') }}</flux:badge>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>

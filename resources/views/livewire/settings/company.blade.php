<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Company settings') }}</flux:heading>

    <x-settings.layout :heading="__('Company')" :subheading="__('Branding, plan usage and your company\'s data')">
        <form wire:submit="saveBranding" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Company name')" type="text" required />

            <flux:input wire:model="brand_color" :label="__('Brand color')" type="text" placeholder="#1d4ed8" :description="__('A hex color, e.g. #1d4ed8. Used on your public community site.')" />

            <div>
                @if ($this->company()->logo_disk_path !== null)
                    <div class="mb-3 flex items-center gap-3">
                        <img src="{{ route('companies.logo', $this->company()) }}" alt="{{ __('Company logo') }}" class="h-12 w-12 rounded object-contain" />
                        <flux:button size="sm" variant="ghost" wire:click="removeLogo" wire:confirm="{{ __('Remove the logo?') }}">{{ __('Remove logo') }}</flux:button>
                    </div>
                @endif
                <flux:input type="file" wire:model="logo" :label="__('Logo')" :description="__('PNG or JPG, up to 2 MB.')" />
            </div>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </form>

        <flux:separator variant="subtle" class="my-8" />

        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Plan usage') }}</flux:heading>

            <ul class="space-y-2 text-sm">
                @foreach ($this->planUsage as $row)
                    <li class="flex items-center justify-between">
                        <span class="text-zinc-600 dark:text-zinc-400">{{ $row['label'] }}</span>
                        <span class="font-medium text-zinc-800 dark:text-zinc-100">
                            {{ $row['used'] }} {{ $row['max'] === null ? __('(unlimited)') : __('/ :max', ['max' => $row['max']]) }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>

        <flux:separator variant="subtle" class="my-8" />

        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ __('Data export') }}</flux:heading>
                <flux:button size="sm" wire:click="requestExport">{{ __('Request export') }}</flux:button>
            </div>

            @if ($this->exportRequests->isEmpty())
                <flux:text class="text-zinc-500">{{ __('No exports requested yet.') }}</flux:text>
            @else
                <ul class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                    @foreach ($this->exportRequests as $export)
                        <li class="flex items-center justify-between px-4 py-3 text-sm" wire:key="export-{{ $export->id }}">
                            <div>
                                <div class="font-medium text-zinc-800 dark:text-zinc-100">{{ $export->status->label() }}</div>
                                <div class="text-zinc-500">{{ $export->requested_at->diffForHumans() }}</div>
                                @if ($export->status === \App\Enums\DataExportStatus::Failed)
                                    <div class="text-red-600 dark:text-red-400">{{ $export->failure_reason }}</div>
                                @endif
                            </div>
                            @if ($export->status === \App\Enums\DataExportStatus::Ready)
                                <flux:button size="sm" :href="route('data-exports.download', $export)">{{ __('Download') }}</flux:button>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </x-settings.layout>
</section>

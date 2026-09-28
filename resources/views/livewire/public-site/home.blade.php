<x-public-site.layout :community="$community">
    <div class="max-w-2xl space-y-4">
        <flux:heading size="xl" level="1">{{ $community->name }}</flux:heading>
        <flux:text class="text-lg">
            {{ __(':type in :city', ['type' => $community->type->label(), 'city' => $community->city ?? $community->region ?? $community->country]) }}
        </flux:text>
        @if ($community->address_line_1)
            <flux:text>
                {{ collect([$community->address_line_1, $community->address_line_2, $community->city, $community->region, $community->postal_code])->filter()->implode(', ') }}
            </flux:text>
        @endif
    </div>

    @if ($this->latestNews->isNotEmpty())
        <div class="mt-12 space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ __('Latest news') }}</flux:heading>
                <flux:link :href="route('public.news', $community->slug)" wire:navigate>{{ __('View all') }}</flux:link>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                @foreach ($this->latestNews as $item)
                    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                        <flux:heading>{{ $item->title }}</flux:heading>
                        <flux:text class="mt-1 line-clamp-3">{{ $item->body }}</flux:text>
                        <flux:text class="mt-2 text-xs text-zinc-500">{{ $item->published_at?->diffForHumans() }}</flux:text>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="mt-12 flex gap-3">
        <flux:button :href="route('public.documents', $community->slug)" variant="filled" wire:navigate>{{ __('Public documents') }}</flux:button>
        <flux:button :href="route('public.contact', $community->slug)" variant="primary" wire:navigate>{{ __('Contact us') }}</flux:button>
    </div>
</x-public-site.layout>

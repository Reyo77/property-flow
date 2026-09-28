<x-public-site.layout :community="$community">
    <flux:heading size="xl" level="1">{{ __('Public documents') }}</flux:heading>

    @if ($this->documents->isEmpty())
        <div class="mt-6 rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <flux:heading>{{ __('No public documents yet') }}</flux:heading>
        </div>
    @else
        <ul class="mt-6 divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
            @foreach ($this->documents as $document)
                <li class="flex items-center justify-between px-4 py-3" wire:key="document-{{ $document->id }}">
                    <flux:text>{{ $document->title }}</flux:text>
                    <flux:button size="sm" :href="route('public.documents.download', [$community->slug, $document])">{{ __('Download') }}</flux:button>
                </li>
            @endforeach
        </ul>
    @endif
</x-public-site.layout>

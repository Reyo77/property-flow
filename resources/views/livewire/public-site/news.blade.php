<x-public-site.layout :community="$community">
    <flux:heading size="xl" level="1">{{ __('News') }}</flux:heading>

    @if ($this->news->isEmpty())
        <div class="mt-6 rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <flux:heading>{{ __('No news yet') }}</flux:heading>
        </div>
    @else
        <div class="mt-6 space-y-6">
            @foreach ($this->news as $item)
                <article class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                    <flux:heading size="lg">{{ $item->title }}</flux:heading>
                    <flux:text class="mt-1 text-xs text-zinc-500">{{ $item->published_at?->format('F j, Y') }}</flux:text>
                    <flux:text class="mt-3 whitespace-pre-line">{{ $item->body }}</flux:text>
                </article>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $this->news->links() }}
        </div>
    @endif
</x-public-site.layout>

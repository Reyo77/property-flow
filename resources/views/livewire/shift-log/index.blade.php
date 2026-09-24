<section class="w-full max-w-2xl space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Shift log') }}</flux:heading>
        <flux:subheading>{{ $community->name }}</flux:subheading>
    </div>

    @if ($this->canPost())
        <form wire:submit="post" class="space-y-3">
            <flux:textarea wire:model="body" :label="__('Add a note')" rows="2" placeholder="{{ __('e.g. All quiet, handled a noise complaint at unit 302...') }}" />
            <flux:button type="submit" size="sm">{{ __('Post') }}</flux:button>
        </form>
    @endif

    <div class="space-y-3">
        @forelse ($this->entries as $entry)
            <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" wire:key="entry-{{ $entry->id }}">
                <div class="flex items-center justify-between">
                    <flux:text class="font-medium text-zinc-800 dark:text-zinc-100">{{ $entry->user?->name ?? __('Unknown') }}</flux:text>
                    <flux:text class="text-xs">{{ $entry->created_at?->format('M j, g:ia') }}</flux:text>
                </div>
                <flux:text class="mt-1 whitespace-pre-line">{{ $entry->body }}</flux:text>
            </div>
        @empty
            <flux:text>{{ __('No shift log entries yet.') }}</flux:text>
        @endforelse
    </div>
</section>

<section class="w-full max-w-2xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <flux:heading size="xl" level="1">{{ __('Notifications') }}</flux:heading>

        @if ($this->notifications->getCollection()->contains(fn ($n) => $n->read_at === null))
            <flux:button size="sm" wire:click="markAllAsRead">{{ __('Mark all read') }}</flux:button>
        @endif
    </div>

    @if ($this->notifications->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <flux:heading>{{ __('No notifications yet') }}</flux:heading>
        </div>
    @else
        <ul class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
            @foreach ($this->notifications as $notification)
                <li wire:key="notification-{{ $notification->id }}">
                    <button type="button" wire:click="open('{{ $notification->id }}')" class="flex w-full items-start gap-3 px-4 py-3 text-start hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                        @if ($notification->read_at === null)
                            <span class="mt-1.5 size-2 shrink-0 rounded-full bg-blue-500" data-test="unread-dot"></span>
                        @else
                            <span class="mt-1.5 size-2 shrink-0"></span>
                        @endif
                        <div class="min-w-0 flex-1">
                            <div class="font-medium text-zinc-800 dark:text-zinc-100">{{ $notification->data['title'] ?? '' }}</div>
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ $notification->data['excerpt'] ?? '' }}</div>
                            <div class="mt-1 text-xs text-zinc-400">{{ $notification->created_at?->diffForHumans() }}</div>
                        </div>
                    </button>
                </li>
            @endforeach
        </ul>

        {{ $this->notifications->links() }}
    @endif
</section>

<div>
    <flux:dropdown position="bottom" align="end">
        <flux:button variant="ghost" icon="bell" :aria-label="__('Notifications')" data-test="notification-bell">
            @if ($this->unreadCount > 0)
                <flux:badge size="sm" color="red" class="absolute -end-1 -top-1" data-test="unread-count">
                    {{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}
                </flux:badge>
            @endif
        </flux:button>

        <flux:menu class="w-80">
            <div class="flex items-center justify-between px-2 py-1.5">
                <flux:heading size="sm">{{ __('Notifications') }}</flux:heading>
                @if ($this->unreadCount > 0)
                    <flux:link class="cursor-pointer text-xs" wire:click.stop="markAllAsRead">{{ __('Mark all read') }}</flux:link>
                @endif
            </div>

            <flux:menu.separator />

            @forelse ($this->recent as $notification)
                <flux:menu.item wire:key="notification-{{ $notification->id }}" wire:click="open('{{ $notification->id }}')" class="items-start">
                    <div class="flex w-full items-start gap-2">
                        @if ($notification->read_at === null)
                            <span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-blue-500"></span>
                        @else
                            <span class="mt-1.5 size-1.5 shrink-0"></span>
                        @endif
                        <div class="min-w-0">
                            <div class="truncate font-medium text-zinc-800 dark:text-zinc-100">{{ $notification->data['title'] ?? '' }}</div>
                            <div class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $notification->data['excerpt'] ?? '' }}</div>
                            <div class="text-xs text-zinc-400">{{ $notification->created_at?->diffForHumans() }}</div>
                        </div>
                    </div>
                </flux:menu.item>
            @empty
                <flux:menu.item disabled>{{ __('No notifications yet') }}</flux:menu.item>
            @endforelse

            <flux:menu.separator />

            <flux:menu.item icon="inbox" :href="route('notifications.index')" wire:navigate>{{ __('See all') }}</flux:menu.item>
        </flux:menu>
    </flux:dropdown>
</div>

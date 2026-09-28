<section class="w-full space-y-6">
    <flux:heading size="xl" level="1">{{ __('Contact messages') }}</flux:heading>
    <flux:subheading>{{ __('Messages submitted through the public website.') }}</flux:subheading>

    @if ($this->messages->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <flux:heading>{{ __('No messages yet') }}</flux:heading>
        </div>
    @else
        <ul class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
            @foreach ($this->messages as $message)
                <li class="px-4 py-3" wire:key="message-{{ $message->id }}" @if (! $message->isRead()) wire:click="markRead({{ $message->id }})" @endif>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                @unless ($message->isRead())
                                    <span class="size-2 shrink-0 rounded-full bg-blue-500" data-test="unread-dot"></span>
                                @endunless
                                <flux:text class="font-medium text-zinc-800 dark:text-zinc-100">{{ $message->name }}</flux:text>
                                <flux:text class="text-zinc-500">{{ $message->email }}</flux:text>
                            </div>
                            <flux:text class="mt-2 whitespace-pre-line">{{ $message->message }}</flux:text>
                            <flux:text class="mt-1 text-xs text-zinc-400">{{ $message->created_at?->diffForHumans() }}</flux:text>
                        </div>
                        <flux:button size="sm" variant="ghost" icon="trash" wire:click.stop="delete({{ $message->id }})" wire:confirm="{{ __('Delete this message?') }}" />
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>

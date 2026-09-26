<section class="w-full max-w-3xl space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:link :href="route('communities.forum.index', [$community, 'tab' => $forumTopic->kind->isClassified() ? 'classifieds' : 'discussion'])" wire:navigate class="text-sm">← {{ __('Community board') }}</flux:link>
            <flux:heading size="xl" level="1">{{ $forumTopic->title }}</flux:heading>
            <flux:subheading>
                {{ $forumTopic->author->name }} · {{ $forumTopic->created_at?->diffForHumans() }}
                @if ($forumTopic->kind->isClassified())
                    · {{ $forumTopic->kind->label() }}{{ $forumTopic->price_cents ? ' · '.App\Support\Finance\Money::of($forumTopic->price_cents)->format() : '' }}
                @endif
            </flux:subheading>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($forumTopic->isHidden())<flux:badge color="red">{{ __('Hidden') }}</flux:badge>@endif
            @if ($forumTopic->closed_at)<flux:badge>{{ __('Sold / gone') }}</flux:badge>@endif
            @can('closeListing', $forumTopic)
                <flux:button size="sm" wire:click="markSold">{{ __('Mark sold / gone') }}</flux:button>
            @endcan
            @if ($this->isModerator())
                <flux:button size="sm" variant="ghost" wire:click="togglePinned">{{ $forumTopic->is_pinned ? __('Unpin') : __('Pin') }}</flux:button>
                <flux:button size="sm" variant="ghost" wire:click="toggleLocked">{{ $forumTopic->isLocked() ? __('Unlock') : __('Lock') }}</flux:button>
                <flux:button size="sm" variant="ghost" wire:click="toggleHidden">{{ $forumTopic->isHidden() ? __('Restore') : __('Hide') }}</flux:button>
            @elseif ($forumTopic->author_id !== auth()->id())
                <flux:button size="sm" variant="ghost" icon="flag" wire:click="startReport">{{ __('Report') }}</flux:button>
            @endif
        </div>
    </div>

    <div class="rounded-xl border border-zinc-200 p-5 whitespace-pre-line dark:border-zinc-700">{{ $forumTopic->body }}</div>

    <div class="space-y-3">
        @foreach ($this->posts as $post)
            <div @class(['rounded-lg p-4', 'bg-zinc-50 dark:bg-zinc-800' => ! $post->isHidden(), 'border border-red-300 opacity-60 dark:border-red-800' => $post->isHidden()]) wire:key="post-{{ $post->id }}">
                <div class="flex items-center justify-between">
                    <flux:text size="sm" variant="strong">{{ $post->author->name }} · {{ $post->created_at?->diffForHumans() }}</flux:text>
                    @if ($this->isModerator())
                        <flux:button size="xs" variant="ghost" wire:click="toggleHidden({{ $post->id }})">{{ $post->isHidden() ? __('Restore') : __('Hide') }}</flux:button>
                    @elseif ($post->author_id !== auth()->id())
                        <flux:button size="xs" variant="ghost" icon="flag" wire:click="startReport({{ $post->id }})" :aria-label="__('Report')" />
                    @endif
                </div>
                <div class="mt-1 whitespace-pre-line text-sm">{{ $post->body }}</div>
            </div>
        @endforeach
    </div>

    @can('reply', $forumTopic)
        @if (! $forumTopic->isLocked() && ! $forumTopic->closed_at && ! $forumTopic->isHidden())
            <form wire:submit="postReply" class="space-y-3">
                <flux:textarea wire:model="reply" :placeholder="__('Write a reply…')" rows="3" />
                <flux:error name="reply" />
                <div class="flex justify-end"><flux:button type="submit" variant="primary">{{ __('Reply') }}</flux:button></div>
            </form>
        @else
            <flux:text size="sm">{{ __('This conversation is closed to new replies.') }}</flux:text>
        @endif
    @endcan

    <flux:modal name="report-form" class="w-full max-w-md">
        <form wire:submit="report" class="space-y-5">
            <flux:heading size="lg">{{ __('Report to the moderators') }}</flux:heading>
            <flux:input wire:model="reason" :label="__('What\'s wrong?')" :placeholder="__('e.g. Spam, rude, not allowed')" required />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Report') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>

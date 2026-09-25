<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Community board') }}</flux:heading>
            <flux:subheading>{{ $community->name }}</flux:subheading>
        </div>
        @can('create', [App\Models\ForumTopic::class, $community])
            <flux:button variant="primary" icon="plus" wire:click="create">{{ $tab === 'classifieds' ? __('New listing') : __('New discussion') }}</flux:button>
        @endcan
    </div>

    @if ($this->openReports->isNotEmpty())
        <div class="space-y-2 rounded-xl border border-amber-300 p-4 dark:border-amber-700" data-test="reports">
            <flux:heading>{{ trans_choice(':count report to review|:count reports to review', $this->openReports->count()) }}</flux:heading>
            @foreach ($this->openReports as $report)
                @php($topic = $report->reportable instanceof App\Models\ForumTopic ? $report->reportable : $report->reportable?->topic)
                <div class="flex flex-wrap items-center justify-between gap-2 text-sm" wire:key="report-{{ $report->id }}">
                    <span>
                        “{{ $report->reason }}” — {{ $report->reportedBy->name }}
                        @if ($topic)
                            · <flux:link :href="route('communities.forum.show', [$community, $topic])" wire:navigate>{{ $topic->title }}</flux:link>
                        @endif
                    </span>
                    <flux:button size="xs" variant="ghost" wire:click="dismissReport({{ $report->id }})">{{ __('Dismiss') }}</flux:button>
                </div>
            @endforeach
        </div>
    @endif

    <flux:radio.group wire:model.live="tab" variant="segmented" class="max-w-xs">
        <flux:radio value="discussion" :label="__('Discussion')" />
        <flux:radio value="classifieds" :label="__('Classifieds')" />
    </flux:radio.group>

    @if ($this->topics->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <flux:heading>{{ __('Nothing posted yet') }}</flux:heading>
        </div>
    @else
        <div class="grid gap-2">
            @foreach ($this->topics as $topic)
                <a href="{{ route('communities.forum.show', [$community, $topic]) }}" wire:navigate wire:key="topic-{{ $topic->id }}"
                   @class(['flex flex-wrap items-center justify-between gap-3 rounded-xl border p-4 hover:bg-zinc-50 dark:hover:bg-zinc-800', 'border-zinc-200 dark:border-zinc-700' => ! $topic->isHidden(), 'border-red-300 opacity-60 dark:border-red-800' => $topic->isHidden()])>
                    <div>
                        <flux:heading>
                            @if ($topic->is_pinned)<flux:icon.bookmark variant="micro" class="inline" />@endif
                            {{ $topic->title }}
                        </flux:heading>
                        <flux:text size="sm">
                            {{ $topic->author->name }} · {{ $topic->last_activity_at->diffForHumans() }}
                            @if ($topic->kind->isClassified())
                                · {{ $topic->kind->label() }}{{ $topic->price_cents ? ' · '.App\Support\Finance\Money::of($topic->price_cents)->format() : '' }}
                            @endif
                        </flux:text>
                    </div>
                    <div class="flex items-center gap-2">
                        @if ($topic->isHidden())<flux:badge size="sm" color="red">{{ __('Hidden') }}</flux:badge>@endif
                        @if ($topic->closed_at)<flux:badge size="sm">{{ __('Sold / gone') }}</flux:badge>@endif
                        @if ($topic->isLocked())<flux:badge size="sm">{{ __('Locked') }}</flux:badge>@endif
                        <flux:text size="sm">{{ trans_choice(':count reply|:count replies', $topic->posts_count) }}</flux:text>
                    </div>
                </a>
            @endforeach
        </div>
        {{ $this->topics->links() }}
    @endif

    @can('create', [App\Models\ForumTopic::class, $community])
        <flux:modal name="topic-form" class="w-full max-w-lg">
            <form wire:submit="post" class="space-y-5">
                <flux:heading size="lg">{{ $tab === 'classifieds' ? __('New listing') : __('New discussion') }}</flux:heading>
                @if ($tab === 'classifieds')
                    <flux:select wire:model.live="kind" :label="__('Type')">
                        @foreach (App\Enums\ForumTopicKind::classifieds() as $option)
                            <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif
                <flux:input wire:model="title" :label="__('Title')" required />
                <flux:textarea wire:model="body" :label="__('Details')" rows="5" required />
                @if ($kind === 'for_sale')
                    <flux:input wire:model="price" :label="__('Price (optional)')" placeholder="0.00" inputmode="decimal" />
                @endif
                <flux:text size="sm">{{ __('Be kind. Posts that break the community rules may be hidden by moderators.') }}</flux:text>
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="filled">{{ __('Cancel') }}</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ __('Post') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endcan
</section>

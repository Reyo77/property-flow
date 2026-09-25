<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Surveys & polls') }}</flux:heading>
            <flux:subheading>{{ $community->name }}</flux:subheading>
        </div>
        @can('create', [App\Models\Survey::class, $community])
            <flux:button variant="primary" icon="plus" :href="route('communities.surveys.create', $community)" wire:navigate>{{ __('New survey or poll') }}</flux:button>
        @endcan
    </div>

    @if ($this->surveys->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <flux:heading>{{ __('No surveys yet') }}</flux:heading>
        </div>
    @else
        <div class="grid gap-3">
            @foreach ($this->surveys as $survey)
                <a href="{{ route('communities.surveys.show', [$community, $survey]) }}" wire:navigate wire:key="survey-{{ $survey->id }}"
                   class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-zinc-200 p-4 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                    <div>
                        <flux:heading>{{ $survey->title }}</flux:heading>
                        <flux:text size="sm">{{ $survey->is_poll ? __('Poll') : __('Survey') }} · {{ $survey->audience->label() }}{{ $survey->is_anonymous ? ' · '.__('Anonymous') : '' }}</flux:text>
                    </div>
                    <div class="flex items-center gap-2">
                        @if (in_array($survey->id, $this->answered, true))
                            <flux:badge size="sm" color="green">{{ __('Answered') }}</flux:badge>
                        @endif
                        @if ($survey->published_at === null)
                            <flux:badge size="sm">{{ __('Draft') }}</flux:badge>
                        @elseif ($survey->isOpen())
                            <flux:badge size="sm" color="blue">{{ __('Open') }}</flux:badge>
                        @else
                            <flux:badge size="sm">{{ __('Closed') }}</flux:badge>
                        @endif
                        <flux:text size="sm">{{ trans_choice(':count response|:count responses', $survey->responses_count) }}</flux:text>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</section>

<section class="w-full max-w-3xl space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ $survey->title }}</flux:heading>
            <flux:subheading>
                {{ $survey->is_poll ? __('Poll') : __('Survey') }} · {{ $survey->audience->label() }}{{ $survey->is_anonymous ? ' · '.__('Anonymous') : '' }}
                @if ($survey->closes_at)
                    · {{ __('closes :date', ['date' => App\Support\Governance\LocalTime::display($survey->closes_at, $community)]) }}
                @endif
            </flux:subheading>
        </div>
        @can('manage', $survey)
            <div class="flex gap-2">
                @if ($survey->published_at === null)
                    <flux:button size="sm" variant="primary" wire:click="publish">{{ __('Publish') }}</flux:button>
                @elseif ($survey->isOpen())
                    <flux:button size="sm" wire:click="closeNow" wire:confirm="{{ __('Close this survey now?') }}">{{ __('Close now') }}</flux:button>
                @endif
            </div>
        @endcan
    </div>

    @if ($survey->description)
        <flux:text class="whitespace-pre-line">{{ $survey->description }}</flux:text>
    @endif

    @if ($this->canAnswer)
        <form wire:submit="submit" class="space-y-6 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            @foreach ($questions as $question)
                <div class="space-y-2" wire:key="answer-{{ $question->id }}">
                    @switch($question->kind)
                        @case(App\Enums\SurveyQuestionKind::SingleChoice)
                            <flux:radio.group wire:model="answers.{{ $question->id }}" :label="$question->title.($question->is_required ? ' *' : '')">
                                @foreach ($question->options as $option)
                                    <flux:radio :value="$option->id" :label="$option->label" />
                                @endforeach
                            </flux:radio.group>
                            @break
                        @case(App\Enums\SurveyQuestionKind::MultipleChoice)
                            <flux:checkbox.group wire:model="answers.{{ $question->id }}" :label="$question->title.($question->is_required ? ' *' : '')">
                                @foreach ($question->options as $option)
                                    <flux:checkbox :value="$option->id" :label="$option->label" />
                                @endforeach
                            </flux:checkbox.group>
                            @break
                        @case(App\Enums\SurveyQuestionKind::Rating)
                            <flux:radio.group wire:model="answers.{{ $question->id }}" :label="$question->title.($question->is_required ? ' *' : '')" variant="segmented" class="max-w-sm">
                                @foreach (range(1, 5) as $rating)
                                    <flux:radio :value="$rating" :label="(string) $rating" />
                                @endforeach
                            </flux:radio.group>
                            @break
                        @default
                            <flux:textarea wire:model="answers.{{ $question->id }}" :label="$question->title.($question->is_required ? ' *' : '')" rows="3" />
                    @endswitch
                    <flux:error name="answers.{{ $question->id }}" />
                </div>
            @endforeach
            <flux:error name="survey" />
            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">{{ __('Submit') }}</flux:button>
            </div>
        </form>
    @elseif ($this->hasAnswered)
        <flux:callout icon="check-circle" variant="success" :heading="__('Thanks — you have answered this.')" />
    @elseif ($survey->published_at !== null && ! $survey->isOpen())
        <flux:callout icon="lock-closed" :heading="__('This survey is closed.')" />
    @endif

    @if ($this->results)
        <div class="space-y-5 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700" data-test="results">
            <flux:heading size="lg">{{ __('Results') }} · {{ trans_choice(':count response|:count responses', $this->results['responses']) }}</flux:heading>
            @foreach ($this->results['questions'] as $question)
                <div class="space-y-2">
                    <flux:text variant="strong">{{ $question['title'] }}</flux:text>
                    @if (isset($question['options']))
                        @foreach ($question['options'] as $option)
                            <div>
                                <div class="flex justify-between text-sm"><span>{{ $option['label'] }}</span><span>{{ $option['count'] }} · {{ $option['percent'] }}%</span></div>
                                <div class="h-2 rounded bg-zinc-200 dark:bg-zinc-700"><div class="h-2 rounded bg-emerald-500" style="width: {{ $option['percent'] }}%"></div></div>
                            </div>
                        @endforeach
                    @elseif (array_key_exists('average', $question))
                        <flux:text>{{ __('Average :average / 5', ['average' => $question['average'] ?? '—']) }}</flux:text>
                    @else
                        @forelse ($question['texts'] as $text)
                            <div class="rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-800">
                                {{ $text['text'] }}
                                @if ($text['name'])
                                    <flux:text size="sm">— {{ $text['name'] }}</flux:text>
                                @endif
                            </div>
                        @empty
                            <flux:text size="sm">{{ __('No written answers.') }}</flux:text>
                        @endforelse
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</section>

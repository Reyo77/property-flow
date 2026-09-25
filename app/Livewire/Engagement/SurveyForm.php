<?php

namespace App\Livewire\Engagement;

use App\Actions\Engagement\SaveSurvey;
use App\Enums\Audience;
use App\Enums\SurveyQuestionKind;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Community;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Support\Governance\LocalTime;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use LogicException;

#[Title('Survey')]
class SurveyForm extends Component
{
    use InteractsWithCurrentUser;

    public Community $community;

    public ?Survey $survey = null;

    public string $title = '';

    public string $description = '';

    public bool $is_poll = false;

    public string $audience = 'residents';

    public bool $is_anonymous = false;

    public string $closes_at = '';

    /**
     * @var list<array{kind: string, title: string, is_required: bool, options: list<string>}>
     */
    public array $questions = [];

    public function mount(): void
    {
        if ($this->survey === null) {
            $this->authorize('create', [Survey::class, $this->community]);

            $this->closes_at = now($this->community->timezone)->addWeeks(2)->setTime(17, 0)->format('Y-m-d\TH:i');
            $this->addQuestion();

            return;
        }

        $this->authorize('manage', $this->survey);
        abort_if($this->survey->published_at !== null, 404);

        $this->title = $this->survey->title;
        $this->description = (string) $this->survey->description;
        $this->is_poll = $this->survey->is_poll;
        $this->audience = $this->survey->audience->value;
        $this->is_anonymous = $this->survey->is_anonymous;
        $this->closes_at = $this->survey->closes_at === null ? '' : LocalTime::forInput($this->survey->closes_at, $this->community);
        $this->questions = array_values($this->survey->questions()->with('options')->get()->map(fn (SurveyQuestion $question) => [
            'kind' => $question->kind->value,
            'title' => $question->title,
            'is_required' => $question->is_required,
            'options' => array_values($question->options->pluck('label')->all()),
        ])->all());
    }

    public function addQuestion(): void
    {
        $this->questions[] = ['kind' => SurveyQuestionKind::SingleChoice->value, 'title' => '', 'is_required' => true, 'options' => ['', '']];
    }

    public function removeQuestion(int $index): void
    {
        $this->questions = array_values(array_filter($this->questions, fn (int $key) => $key !== $index, ARRAY_FILTER_USE_KEY));
    }

    public function addOption(int $question): void
    {
        if (isset($this->questions[$question])) {
            $this->questions[$question]['options'][] = '';
        }
    }

    public function save(SaveSurvey $saveSurvey): void
    {
        if ($this->survey === null) {
            $this->authorize('create', [Survey::class, $this->community]);
        } else {
            $this->authorize('manage', $this->survey);
        }

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_poll' => ['boolean'],
            'audience' => ['required', Rule::enum(Audience::class)],
            'is_anonymous' => ['boolean'],
            'closes_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'questions' => ['required', 'array', 'min:1', 'max:30'],
            'questions.*.kind' => ['required', Rule::enum(SurveyQuestionKind::class)],
            'questions.*.title' => ['required', 'string', 'max:500'],
            'questions.*.is_required' => ['boolean'],
            'questions.*.options' => ['array', 'max:15'],
            'questions.*.options.*' => ['nullable', 'string', 'max:255'],
        ]);

        $questions = array_values(array_map(fn (array $question) => [
            'kind' => $question['kind'],
            'title' => $question['title'],
            'is_required' => (bool) ($question['is_required'] ?? true),
            'options' => array_values(array_filter(array_map('trim', $question['options'] ?? []), fn (string $option) => $option !== '')),
        ], $validated['questions']));

        try {
            $survey = $saveSurvey->handle($this->community, $this->survey, [
                'title' => $validated['title'],
                'description' => $validated['description'] ?: null,
                'is_poll' => (bool) $validated['is_poll'],
                'audience' => $validated['audience'],
                'is_anonymous' => (bool) $validated['is_anonymous'],
                'closes_at' => $validated['closes_at'] ? LocalTime::toUtc($validated['closes_at'], $this->community)->toDateTimeString() : null,
            ], $questions, $this->currentUser());
        } catch (LogicException $exception) {
            $this->addError('questions', $exception->getMessage());

            return;
        }

        $this->redirectRoute('communities.surveys.show', [$this->community, $survey], navigate: true);
    }

    public function render(): View
    {
        return view('livewire.engagement.survey-form');
    }
}

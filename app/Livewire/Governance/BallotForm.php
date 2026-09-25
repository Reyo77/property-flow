<?php

namespace App\Livewire\Governance;

use App\Actions\Governance\SaveBallot;
use App\Concerns\GovernanceValidationRules;
use App\Enums\MeetingKind;
use App\Enums\VotingWeighting;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Ballot;
use App\Models\Community;
use App\Models\Meeting;
use App\Support\Governance\LocalTime;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use LogicException;

/**
 * Writing (or rewriting, while it's still a draft) a ballot and its questions.
 */
#[Title('Ballot')]
class BallotForm extends Component
{
    use GovernanceValidationRules, InteractsWithCurrentUser;

    public Community $community;

    public ?Ballot $ballot = null;

    public string $title = '';

    public string $description = '';

    public string $meeting_id = '';

    public string $weighting = '';

    public int $quorum_percent = 25;

    public string $opens_at = '';

    public string $closes_at = '';

    /**
     * @var list<array{title: string, options: list<string>}>
     */
    public array $questions = [];

    public function mount(): void
    {
        if ($this->ballot === null) {
            $this->authorize('create', [Ballot::class, $this->community]);

            $this->weighting = VotingWeighting::UnitFactor->value;
            $this->meeting_id = (string) request()->integer('meeting') ?: '';
            $start = now($this->community->timezone)->addDay()->setTime(9, 0);
            $this->opens_at = $start->format('Y-m-d\TH:i');
            $this->closes_at = $start->addWeeks(2)->setTime(17, 0)->format('Y-m-d\TH:i');
            $this->addQuestion();

            return;
        }

        $this->authorize('update', $this->ballot);

        $this->title = $this->ballot->title;
        $this->description = (string) $this->ballot->description;
        $this->meeting_id = (string) ($this->ballot->meeting_id ?? '');
        $this->weighting = $this->ballot->weighting->value;
        $this->quorum_percent = $this->ballot->quorum_percent;
        $this->opens_at = LocalTime::forInput($this->ballot->opens_at, $this->community);
        $this->closes_at = LocalTime::forInput($this->ballot->closes_at, $this->community);
        $this->questions = array_values($this->ballot->questions()->with('options')->get()->map(fn ($question) => [
            'title' => $question->title,
            'options' => array_values($question->options->pluck('label')->all()),
        ])->all());
    }

    /**
     * @return Collection<int, Meeting>
     */
    #[Computed]
    public function meetings(): Collection
    {
        return $this->community->meetings()->whereNull('closed_at')->whereIn('kind', [MeetingKind::Agm, MeetingKind::Special])->orderBy('starts_at')->get();
    }

    public function addQuestion(): void
    {
        $this->questions[] = ['title' => '', 'options' => [__('Yes'), __('No'), __('Abstain')]];
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

    public function removeOption(int $question, int $option): void
    {
        if (isset($this->questions[$question])) {
            $this->questions[$question]['options'] = array_values(array_filter($this->questions[$question]['options'], fn (int $key) => $key !== $option, ARRAY_FILTER_USE_KEY));
        }
    }

    public function save(SaveBallot $saveBallot): void
    {
        $this->ballot === null
            ? $this->authorize('create', [Ballot::class, $this->community])
            : $this->authorize('update', $this->ballot);

        $validated = $this->validate($this->ballotRules($this->community));

        try {
            $ballot = $saveBallot->handle(
                $this->community,
                $this->ballot,
                [
                    'meeting_id' => $validated['meeting_id'] ? (int) $validated['meeting_id'] : null,
                    'title' => $validated['title'],
                    'description' => $validated['description'] ?: null,
                    'weighting' => $validated['weighting'],
                    'quorum_percent' => (int) $validated['quorum_percent'],
                    'opens_at' => LocalTime::toUtc($validated['opens_at'], $this->community)->toDateTimeString(),
                    'closes_at' => LocalTime::toUtc($validated['closes_at'], $this->community)->toDateTimeString(),
                ],
                array_values(array_map(fn (array $question) => [
                    'title' => $question['title'],
                    'options' => array_values($question['options']),
                ], $validated['questions'])),
                $this->currentUser(),
            );
        } catch (LogicException $exception) {
            $this->addError('title', $exception->getMessage());

            return;
        }

        $this->redirectRoute('communities.ballots.show', [$this->community, $ballot], navigate: true);
    }

    public function render(): View
    {
        return view('livewire.governance.ballot-form');
    }
}

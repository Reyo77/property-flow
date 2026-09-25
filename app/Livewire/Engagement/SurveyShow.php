<?php

namespace App\Livewire\Engagement;

use App\Actions\Engagement\SaveSurvey;
use App\Actions\Engagement\SubmitSurveyResponse;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Community;
use App\Models\Survey;
use App\Support\Governance\AudienceCheck;
use App\Support\Governance\SurveyResults;
use Flux\Flux;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use LogicException;

#[Title('Survey')]
class SurveyShow extends Component
{
    use InteractsWithCurrentUser;

    public Community $community;

    public Survey $survey;

    /**
     * @var array<int, int|string|list<int|string>|null>
     */
    public array $answers = [];

    public function mount(): void
    {
        $this->authorize('view', $this->survey);
    }

    #[Computed]
    public function hasAnswered(): bool
    {
        return $this->survey->responses()->where('user_id', $this->currentUser()->id)->exists();
    }

    #[Computed]
    public function canAnswer(): bool
    {
        return $this->survey->isOpen() && ! $this->hasAnswered()
            && app(AudienceCheck::class)->includes($this->currentUser(), $this->survey->community_id, $this->survey->audience);
    }

    /**
     * @return array{responses: int, questions: list<array<string, mixed>>}|null
     */
    #[Computed]
    public function results(): ?array
    {
        return $this->currentUser()->can('viewResults', $this->survey) ? app(SurveyResults::class)->for($this->survey) : null;
    }

    public function submit(SubmitSurveyResponse $submit): void
    {
        try {
            $submit->handle($this->survey, $this->currentUser(), $this->answers);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        } catch (AuthorizationException $exception) {
            $this->addError('survey', $exception->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Thanks — your answers are in.'));
        unset($this->hasAnswered, $this->canAnswer, $this->results);
    }

    public function publish(SaveSurvey $saveSurvey): void
    {
        $this->authorize('manage', $this->survey);

        try {
            $saveSurvey->publish($this->survey);
        } catch (LogicException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $this->survey->refresh();
        unset($this->canAnswer);
    }

    public function closeNow(): void
    {
        $this->authorize('manage', $this->survey);

        $this->survey->forceFill(['closes_at' => now()])->save();
        unset($this->canAnswer);
    }

    public function render(): View
    {
        return view('livewire.engagement.survey-show', ['questions' => $this->survey->questions()->with('options')->get()]);
    }
}

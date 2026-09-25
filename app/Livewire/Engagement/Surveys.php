<?php

namespace App\Livewire\Engagement;

use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Community;
use App\Models\Survey;
use App\Models\SurveyResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Surveys & polls')]
class Surveys extends Component
{
    use InteractsWithCurrentUser;

    public Community $community;

    public function mount(): void
    {
        $this->authorize('viewAny', [Survey::class, $this->community]);
    }

    /**
     * @return Collection<int, Survey>
     */
    #[Computed]
    public function surveys(): Collection
    {
        $user = $this->currentUser();

        return $this->community->surveys()->withCount('responses')->latest()->get()
            ->filter(fn (Survey $survey) => $user->can('view', $survey))
            ->values();
    }

    /**
     * @return list<int>
     */
    #[Computed]
    public function answered(): array
    {
        return array_values(SurveyResponse::query()->where('user_id', $this->currentUser()->id)->pluck('survey_id')->map(fn ($id) => (int) $id)->all());
    }

    public function render(): View
    {
        return view('livewire.engagement.surveys');
    }
}

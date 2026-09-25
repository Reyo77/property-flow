<?php

namespace App\Livewire\Engagement;

use App\Enums\Audience;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Community;
use App\Models\ConsentForm;
use App\Models\ConsentSignature;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Forms to sign')]
class ConsentForms extends Component
{
    use InteractsWithCurrentUser;

    public Community $community;

    public string $title = '';

    public string $body = '';

    public string $audience = 'owners';

    public function mount(): void
    {
        $this->authorize('viewAny', [ConsentForm::class, $this->community]);
    }

    /**
     * @return Collection<int, ConsentForm>
     */
    #[Computed]
    public function forms(): Collection
    {
        $user = $this->currentUser();

        return $this->community->consentForms()->withCount('signatures')->latest()->get()
            ->filter(fn (ConsentForm $form) => $user->can('view', $form))
            ->values();
    }

    /**
     * @return list<int>
     */
    #[Computed]
    public function signed(): array
    {
        return array_values(ConsentSignature::query()->where('user_id', $this->currentUser()->id)->pluck('consent_form_id')->map(fn ($id) => (int) $id)->all());
    }

    public function create(): void
    {
        $this->authorize('create', [ConsentForm::class, $this->community]);

        $this->resetValidation();
        $this->reset('title', 'body');
        Flux::modal('consent-form')->show();
    }

    public function save(): void
    {
        $this->authorize('create', [ConsentForm::class, $this->community]);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:50000'],
            'audience' => ['required', Rule::enum(Audience::class)],
        ]);

        $form = new ConsentForm($validated);
        $form->forceFill(['company_id' => $this->community->company_id, 'community_id' => $this->community->id, 'created_by_id' => $this->currentUser()->id])->save();

        Flux::modal('consent-form')->close();
        $this->redirectRoute('communities.consent-forms.show', [$this->community, $form], navigate: true);
    }

    public function render(): View
    {
        return view('livewire.engagement.consent-forms');
    }
}

<?php

namespace App\Livewire\ArchitecturalRequests;

use App\Actions\ArchitecturalRequests\DecideArchitecturalRequest;
use App\Enums\ArchitecturalRequestStatus;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\ArchitecturalRequest;
use App\Models\Community;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;
use LogicException;

#[Title('Renovation request')]
class Show extends Component
{
    use InteractsWithCurrentUser;

    public Community $community;

    public ArchitecturalRequest $architecturalRequest;

    public string $decision = '';

    public string $conditions = '';

    public string $decision_notes = '';

    public function mount(): void
    {
        $this->authorize('view', $this->architecturalRequest);

        $this->decision = ArchitecturalRequestStatus::Approved->value;
    }

    public function startReview(DecideArchitecturalRequest $decide): void
    {
        $this->authorize('decide', $this->architecturalRequest);

        try {
            $decide->startReview($this->architecturalRequest, $this->currentUser());
        } catch (LogicException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $this->architecturalRequest->refresh();
    }

    public function decide(DecideArchitecturalRequest $decide): void
    {
        $this->authorize('decide', $this->architecturalRequest);

        $status = match ($this->decision) {
            'approved' => ArchitecturalRequestStatus::Approved,
            'approved_with_conditions' => ArchitecturalRequestStatus::ApprovedWithConditions,
            'denied' => ArchitecturalRequestStatus::Denied,
            default => null,
        };

        if ($status === null) {
            $this->addError('decision', __('Choose a decision.'));

            return;
        }

        $this->validate(['conditions' => ['nullable', 'string', 'max:5000'], 'decision_notes' => ['nullable', 'string', 'max:5000']]);

        try {
            $decide->decide($this->architecturalRequest, $status, $this->conditions ?: null, $this->decision_notes ?: null, $this->currentUser());
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        } catch (LogicException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $this->architecturalRequest->refresh();
        Flux::toast(variant: 'success', text: __('Decision recorded and the owner notified.'));
    }

    public function withdraw(DecideArchitecturalRequest $decide): void
    {
        $this->authorize('withdraw', $this->architecturalRequest);

        $decide->withdraw($this->architecturalRequest, $this->currentUser());
        $this->architecturalRequest->refresh();
    }

    public function render(): View
    {
        return view('livewire.architectural-requests.show', ['plans' => $this->architecturalRequest->attachments()->get()]);
    }
}

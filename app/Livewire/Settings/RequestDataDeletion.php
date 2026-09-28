<?php

namespace App\Livewire\Settings;

use App\Actions\Residents\RequestDataDeletion as RequestDataDeletionAction;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Resident;
use App\Models\ResidentDataDeletionRequest;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Component;

class RequestDataDeletion extends Component
{
    use InteractsWithCurrentUser;

    public string $notes = '';

    public function mount(): void
    {
        $this->authorize('create', ResidentDataDeletionRequest::class);
    }

    public function request(RequestDataDeletionAction $requestDataDeletion): void
    {
        $this->authorize('create', ResidentDataDeletionRequest::class);

        $requestDataDeletion->handle($this->resident(), $this->currentUser(), $this->notes === '' ? null : $this->notes);

        $this->reset('notes');
        unset($this->pendingRequest);

        Flux::toast(variant: 'success', text: __('Your request has been sent for review.'));
    }

    #[Computed]
    public function pendingRequest(): ?ResidentDataDeletionRequest
    {
        return $this->resident()->dataDeletionRequests()->latest('id')->first();
    }

    public function render(): View
    {
        return view('livewire.settings.request-data-deletion');
    }

    private function resident(): Resident
    {
        return $this->currentUser()->resident ?? throw new InvalidArgumentException('Only residents can request data deletion.');
    }
}

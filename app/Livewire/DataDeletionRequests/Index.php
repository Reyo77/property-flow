<?php

namespace App\Livewire\DataDeletionRequests;

use App\Actions\Residents\DecideDataDeletionRequest;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\ResidentDataDeletionRequest;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Data deletion requests')]
class Index extends Component
{
    use InteractsWithCurrentUser;

    #[Locked]
    public ?int $decidingRequestId = null;

    public string $decisionNotes = '';

    public function mount(): void
    {
        $this->authorize('viewAny', ResidentDataDeletionRequest::class);
    }

    /**
     * @return Collection<int, ResidentDataDeletionRequest>
     */
    #[Computed]
    public function requests(): Collection
    {
        return ResidentDataDeletionRequest::query()
            ->with(['resident' => fn ($query) => $query->withTrashed(), 'requestedBy', 'reviewedBy'])
            ->orderByRaw("status = 'pending' desc")
            ->latest('id')
            ->get();
    }

    public function openDecision(int $requestId): void
    {
        $request = $this->findRequest($requestId);

        $this->authorize('review', $request);

        $this->resetValidation();
        $this->decisionNotes = '';
        $this->decidingRequestId = $requestId;

        Flux::modal('decide-request')->show();
    }

    public function approve(DecideDataDeletionRequest $decideDataDeletionRequest): void
    {
        $this->decide($decideDataDeletionRequest, approve: true);
    }

    public function deny(DecideDataDeletionRequest $decideDataDeletionRequest): void
    {
        $this->decide($decideDataDeletionRequest, approve: false);
    }

    public function render(): View
    {
        return view('livewire.data-deletion-requests.index');
    }

    private function decide(DecideDataDeletionRequest $decideDataDeletionRequest, bool $approve): void
    {
        $request = $this->findRequest((int) $this->decidingRequestId);

        $this->authorize('review', $request);

        $decideDataDeletionRequest->handle($request, $this->currentUser(), $approve, $this->decisionNotes === '' ? null : $this->decisionNotes);

        Flux::modal('decide-request')->close();
        Flux::toast(variant: 'success', text: $approve ? __('Request approved. The resident\'s data has been erased.') : __('Request denied.'));
        unset($this->requests);
    }

    private function findRequest(int $requestId): ResidentDataDeletionRequest
    {
        return ResidentDataDeletionRequest::query()->findOrFail($requestId);
    }
}

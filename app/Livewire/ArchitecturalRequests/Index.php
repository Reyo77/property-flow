<?php

namespace App\Livewire\ArchitecturalRequests;

use App\Actions\ArchitecturalRequests\SubmitArchitecturalRequest;
use App\Enums\ArchitecturalRequestStatus;
use App\Enums\Permission;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\ArchitecturalRequest;
use App\Models\Community;
use App\Models\Unit;
use App\Support\Governance\VotingRoll;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Renovation and alteration requests: owners submit and follow their own; the board and
 * managers see them all.
 */
#[Title('Renovation requests')]
class Index extends Component
{
    use InteractsWithCurrentUser, WithFileUploads;

    public Community $community;

    public string $unit_id = '';

    public string $title = '';

    public string $description = '';

    public string $contractor = '';

    public string $planned_start_on = '';

    /**
     * @var list<TemporaryUploadedFile>
     */
    public array $plans = [];

    public function mount(): void
    {
        $this->authorize('viewAny', [ArchitecturalRequest::class, $this->community]);
    }

    #[Computed]
    public function isReviewer(): bool
    {
        $user = $this->currentUser();

        return $user->canAccessCommunity($this->community) && $user->hasCompanyPermission(Permission::ViewGovernance);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Unit>
     */
    #[Computed]
    public function ownedUnits(): \Illuminate\Support\Collection
    {
        return app(VotingRoll::class)->unitsOwnedBy($this->currentUser(), $this->community);
    }

    /**
     * @return Collection<int, ArchitecturalRequest>
     */
    #[Computed]
    public function requests(): Collection
    {
        $query = $this->community->architecturalRequests()->with('unit.building')->latest();

        if (! $this->isReviewer()) {
            $user = $this->currentUser();
            $query->where(fn ($query) => $query->where('submitted_by_id', $user->id)->orWhereIn('unit_id', $this->ownedUnits()->pluck('id')));
        }

        return $query->get();
    }

    public function create(): void
    {
        $this->authorize('create', [ArchitecturalRequest::class, $this->community]);

        $this->resetValidation();
        $this->reset('title', 'description', 'contractor', 'planned_start_on', 'plans');
        $this->unit_id = (string) ($this->ownedUnits()->first()->id ?? '');

        Flux::modal('request-form')->show();
    }

    public function submit(SubmitArchitecturalRequest $submit): void
    {
        $this->authorize('create', [ArchitecturalRequest::class, $this->community]);

        $validated = $this->validate([
            'unit_id' => ['required', 'integer', 'in:'.$this->ownedUnits()->pluck('id')->implode(',')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'contractor' => ['nullable', 'string', 'max:255'],
            'planned_start_on' => ['nullable', 'date', 'after_or_equal:today'],
            'plans' => ['array', 'max:10'],
            'plans.*' => ['file', 'mimes:pdf,jpg,jpeg,png,heic', 'max:20480'],
        ]);

        try {
            $request = $submit->handle(
                $this->community->units()->findOrFail((int) $validated['unit_id']),
                $this->currentUser(),
                $validated['title'],
                $validated['description'],
                $validated['contractor'] ?: null,
                $validated['planned_start_on'] ? CarbonImmutable::parse($validated['planned_start_on']) : null,
                $this->plans,
            );
        } catch (AuthorizationException $exception) {
            $this->addError('unit_id', $exception->getMessage());

            return;
        }

        Flux::modal('request-form')->close();
        $this->redirectRoute('communities.architectural-requests.show', [$this->community, $request], navigate: true);
    }

    public function render(): View
    {
        return view('livewire.architectural-requests.index', ['statuses' => ArchitecturalRequestStatus::class]);
    }
}

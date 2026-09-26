<?php

namespace App\Livewire\ServiceRequests;

use App\Actions\Maintenance\CreateServiceRequest;
use App\Concerns\ServiceRequestValidationRules;
use App\Enums\Permission;
use App\Enums\ServiceRequestCategory;
use App\Enums\ServiceRequestPriority;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Community;
use App\Models\ServiceRequest;
use App\Models\Unit;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Title('New service request')]
class Create extends Component
{
    use InteractsWithCurrentUser, ServiceRequestValidationRules, WithFileUploads;

    public Community $community;

    public string $unit_id = '';

    public string $title = '';

    public string $description = '';

    public string $category = '';

    public string $priority = ServiceRequestPriority::Medium->value;

    public bool $entry_permission = false;

    /** @var list<TemporaryUploadedFile> */
    public array $photos = [];

    public function mount(): void
    {
        $this->authorize('create', [ServiceRequest::class, $this->community]);

        $myUnits = $this->myUnits();

        if ($myUnits->count() === 1) {
            $this->unit_id = (string) $myUnits->first()?->id;
        }
    }

    /**
     * @return Collection<int, Unit>
     */
    #[Computed]
    public function myUnits(): Collection
    {
        $resident = $this->currentUser()->resident;

        if ($resident === null) {
            return new Collection;
        }

        $unitIds = $resident->residencies()->active()->pluck('unit_id');

        return Unit::query()->whereIn('id', $unitIds)->with('building')->get();
    }

    /**
     * Team members log a request for any unit; residents may only pick from their own.
     */
    #[Computed]
    public function canPickAnyUnit(): bool
    {
        $user = $this->currentUser();

        return $user->canAccessCommunity($this->community) && $user->hasCompanyPermission(Permission::ManageServiceRequests);
    }

    /**
     * @return Collection<int, Unit>
     */
    #[Computed]
    public function communityUnits(): Collection
    {
        return $this->canPickAnyUnit()
            ? $this->community->units()->with('building')->orderBy('number')->get()
            : new Collection;
    }

    public function save(CreateServiceRequest $createServiceRequest): void
    {
        $this->authorize('create', [ServiceRequest::class, $this->community]);

        $validated = $this->validate($this->serviceRequestRules($this->community));
        $unitId = $validated['unit_id'] === '' || $validated['unit_id'] === null ? null : (int) $validated['unit_id'];

        $serviceRequest = $createServiceRequest->handle(
            $this->community,
            $this->currentUser(),
            [
                'title' => $validated['title'],
                'description' => $validated['description'],
                'category' => $validated['category'],
                'priority' => $validated['priority'],
                'unit_id' => $unitId,
                'entry_permission' => $this->entry_permission,
            ],
            $this->photos,
        );

        $this->redirectRoute('communities.service-requests.show', [$this->community, $serviceRequest], navigate: true);
    }

    /**
     * @return list<ServiceRequestCategory>
     */
    public function categories(): array
    {
        return ServiceRequestCategory::cases();
    }

    /**
     * @return list<ServiceRequestPriority>
     */
    public function priorities(): array
    {
        return ServiceRequestPriority::cases();
    }

    public function render(): View
    {
        return view('livewire.service-requests.create');
    }
}

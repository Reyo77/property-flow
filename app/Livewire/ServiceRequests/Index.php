<?php

namespace App\Livewire\ServiceRequests;

use App\Enums\Permission;
use App\Enums\ServiceRequestCategory;
use App\Enums\ServiceRequestPriority;
use App\Enums\ServiceRequestStatus;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Community;
use App\Models\ServiceRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Service requests')]
class Index extends Component
{
    use InteractsWithCurrentUser, WithPagination;

    public Community $community;

    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';

    #[Url(as: 'category', except: '')]
    public string $categoryFilter = '';

    #[Url(as: 'priority', except: '')]
    public string $priorityFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', [ServiceRequest::class, $this->community]);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['statusFilter', 'categoryFilter', 'priorityFilter'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function isTeamViewer(): bool
    {
        $user = $this->currentUser();

        return $user->canAccessCommunity($this->community) && $user->hasCompanyPermission(Permission::ViewServiceRequests);
    }

    /**
     * @return LengthAwarePaginator<int, ServiceRequest>
     */
    #[Computed]
    public function serviceRequests(): LengthAwarePaginator
    {
        $query = $this->community->serviceRequests()->with(['unit.building', 'workOrders']);

        if (! $this->isTeamViewer()) {
            $resident = $this->currentUser()->resident;
            $unitIds = $resident?->residencies()->active()->pluck('unit_id') ?? collect();

            $query->where(function (Builder $query) use ($resident, $unitIds): void {
                $query->whereIn('unit_id', $unitIds);

                if ($resident !== null) {
                    $query->orWhere('reported_by_resident_id', $resident->id);
                }
            });
        }

        return $query
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->when($this->categoryFilter !== '', fn (Builder $query) => $query->where('category', $this->categoryFilter))
            ->when($this->priorityFilter !== '', fn (Builder $query) => $query->where('priority', $this->priorityFilter))
            ->orderByRaw("status = 'closed'")
            ->latest('created_at')
            ->paginate(20);
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

    /**
     * @return list<ServiceRequestStatus>
     */
    public function statuses(): array
    {
        return ServiceRequestStatus::cases();
    }

    public function render(): View
    {
        return view('livewire.service-requests.index');
    }
}

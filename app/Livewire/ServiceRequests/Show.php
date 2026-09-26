<?php

namespace App\Livewire\ServiceRequests;

use App\Actions\Maintenance\CreateWorkOrder;
use App\Actions\Maintenance\PostServiceRequestComment;
use App\Actions\Maintenance\TransitionServiceRequestStatus;
use App\Actions\Maintenance\TransitionWorkOrderStatus;
use App\Concerns\ServiceRequestValidationRules;
use App\Concerns\WorkOrderValidationRules;
use App\Enums\Assignee;
use App\Enums\ServiceRequestStatus;
use App\Enums\WorkOrderStatus;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Community;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestComment;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WorkOrder;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use LogicException;

#[Title('Service request')]
class Show extends Component
{
    use InteractsWithCurrentUser, ServiceRequestValidationRules, WorkOrderValidationRules;

    public Community $community;

    public ServiceRequest $serviceRequest;

    public string $body = '';

    public bool $commentIsInternal = false;

    public string $title = '';

    public string $description = '';

    public string $assignee_type = '';

    public string $assigned_user_id = '';

    public string $assigned_vendor_id = '';

    public string $due_on = '';

    public string $completionNotes = '';

    public function mount(): void
    {
        $this->authorize('view', $this->serviceRequest);
    }

    #[Computed]
    public function canManage(): bool
    {
        return $this->currentUser()->can('manage', $this->serviceRequest);
    }

    #[Computed]
    public function canAddInternalComment(): bool
    {
        return $this->currentUser()->can('addInternalComment', $this->serviceRequest);
    }

    /**
     * @return Collection<int, ServiceRequestComment>
     */
    #[Computed]
    public function comments(): Collection
    {
        return $this->serviceRequest->comments()
            ->when(! $this->canAddInternalComment(), fn (Builder $query) => $query->where('visible_to_resident', true))
            ->with('author')
            ->oldest()
            ->get();
    }

    #[Computed]
    public function workOrder(): ?WorkOrder
    {
        return $this->serviceRequest->currentWorkOrder();
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function staffOptions(): Collection
    {
        return $this->community->users()->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Vendor>
     */
    #[Computed]
    public function vendorOptions(): Collection
    {
        return Vendor::query()->orderBy('name')->get();
    }

    public function postComment(PostServiceRequestComment $postComment): void
    {
        $this->authorize('comment', $this->serviceRequest);

        $validated = $this->validate($this->serviceRequestCommentRules());

        $postComment->handle($this->serviceRequest, $this->currentUser(), $validated['body'], $this->commentIsInternal && $this->canAddInternalComment());

        $this->reset('body', 'commentIsInternal');
        unset($this->comments);
    }

    public function transitionTo(string $status, TransitionServiceRequestStatus $transitionServiceRequestStatus): void
    {
        $this->authorize('manage', $this->serviceRequest);

        try {
            $transitionServiceRequestStatus->handle($this->serviceRequest, ServiceRequestStatus::from($status));
        } catch (LogicException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Status updated.'));
        $this->serviceRequest->refresh();
    }

    public function openWorkOrderForm(): void
    {
        $this->authorize('manage', $this->serviceRequest);

        $this->resetValidation();
        $this->reset('title', 'description', 'assignee_type', 'assigned_user_id', 'assigned_vendor_id', 'due_on');
        $this->title = __('Work: :title', ['title' => $this->serviceRequest->title]);

        Flux::modal('work-order-form')->show();
    }

    public function createWorkOrder(CreateWorkOrder $createWorkOrder): void
    {
        $this->authorize('manage', $this->serviceRequest);

        $validated = $this->validate($this->workOrderRules($this->community, $this->assignee_type));

        try {
            $createWorkOrder->handle($this->community, $this->currentUser(), $this->serviceRequest, [
                'title' => $validated['title'],
                'description' => $validated['description'] === '' ? null : ($validated['description'] ?? null),
                'assignee_type' => $validated['assignee_type'],
                'assigned_user_id' => $validated['assigned_user_id'] ?? null,
                'assigned_vendor_id' => $validated['assigned_vendor_id'] ?? null,
                'due_on' => $validated['due_on'] === '' ? null : ($validated['due_on'] ?? null),
            ]);
        } catch (ValidationException $exception) {
            Flux::toast(variant: 'danger', text: $exception->validator->errors()->first());

            return;
        }

        Flux::modal('work-order-form')->close();
        Flux::toast(variant: 'success', text: __('Work order created.'));
        $this->serviceRequest->refresh();
        unset($this->workOrder);
    }

    public function transitionWorkOrderTo(string $status, TransitionWorkOrderStatus $transitionWorkOrderStatus): void
    {
        $workOrder = $this->workOrder();

        if ($workOrder === null) {
            return;
        }

        $this->authorize('updateProgress', $workOrder);

        try {
            $transitionWorkOrderStatus->handle(
                $workOrder,
                WorkOrderStatus::from($status),
                $status === WorkOrderStatus::Completed->value ? ($this->completionNotes !== '' ? $this->completionNotes : null) : null,
            );
        } catch (LogicException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $this->reset('completionNotes');
        Flux::toast(variant: 'success', text: __('Work order updated.'));
        $this->serviceRequest->refresh();
        unset($this->workOrder);
    }

    /**
     * @return list<Assignee>
     */
    public function assigneeTypes(): array
    {
        return Assignee::cases();
    }

    /**
     * @return list<ServiceRequestStatus>
     */
    public function nextStatuses(): array
    {
        return $this->serviceRequest->status->allowedNextStatuses();
    }

    public function render(): View
    {
        return view('livewire.service-requests.show');
    }
}

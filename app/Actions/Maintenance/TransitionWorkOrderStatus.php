<?php

namespace App\Actions\Maintenance;

use App\Enums\ServiceRequestStatus;
use App\Enums\WebhookEvent;
use App\Enums\WorkOrderStatus;
use App\Models\ServiceRequest;
use App\Models\WorkOrder;
use App\Support\Webhooks\Webhooks;
use LogicException;

/**
 * Moves a work order's status, keeping the service request it came from in step: starting work
 * moves the request to "in progress", and completing the job resolves it.
 */
class TransitionWorkOrderStatus
{
    public function __construct(private readonly TransitionServiceRequestStatus $transitionServiceRequestStatus) {}

    /**
     * @throws LogicException
     */
    public function handle(WorkOrder $workOrder, WorkOrderStatus $target, ?string $completionNotes = null): void
    {
        $workOrder->transitionTo($target);

        if ($completionNotes !== null) {
            $workOrder->forceFill(['completion_notes' => $completionNotes])->save();
        }

        app(Webhooks::class)->dispatch(WebhookEvent::WorkOrderStatusChanged, $workOrder);

        $serviceRequest = $workOrder->serviceRequest;

        if ($serviceRequest === null) {
            return;
        }

        match ($target) {
            WorkOrderStatus::InProgress => $this->cascade($serviceRequest, ServiceRequestStatus::InProgress),
            WorkOrderStatus::Completed => $this->cascade($serviceRequest, ServiceRequestStatus::Resolved),
            default => null,
        };
    }

    private function cascade(ServiceRequest $serviceRequest, ServiceRequestStatus $target): void
    {
        if ($serviceRequest->status->canTransitionTo($target)) {
            $this->transitionServiceRequestStatus->handle($serviceRequest, $target);
        }
    }
}

<?php

namespace App\Actions\Maintenance;

use App\Enums\ServiceRequestStatus;
use App\Models\Community;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateWorkOrder
{
    public function __construct(private readonly TransitionServiceRequestStatus $transitionServiceRequestStatus) {}

    /**
     * @param  array{title: string, description: string|null, assignee_type: string, assigned_user_id: int|null, assigned_vendor_id: int|null, due_on: string|null}  $validated
     *
     * @throws ValidationException
     */
    public function handle(Community $community, User $actor, ?ServiceRequest $serviceRequest, array $validated): WorkOrder
    {
        if ($serviceRequest !== null) {
            if (in_array($serviceRequest->status, [ServiceRequestStatus::Resolved, ServiceRequestStatus::Closed], true)) {
                throw ValidationException::withMessages(['service_request' => __('This request is already :status.', ['status' => $serviceRequest->status->label()])]);
            }

            if ($serviceRequest->currentWorkOrder() !== null) {
                throw ValidationException::withMessages(['service_request' => __('This request already has an active work order.')]);
            }
        }

        return DB::transaction(function () use ($community, $actor, $serviceRequest, $validated): WorkOrder {
            $workOrder = $community->workOrders()->make($validated);
            $workOrder->forceFill([
                'service_request_id' => $serviceRequest?->id,
                'created_by_id' => $actor->id,
            ])->save();

            if ($serviceRequest !== null && $serviceRequest->status === ServiceRequestStatus::Open) {
                $this->transitionServiceRequestStatus->handle($serviceRequest, ServiceRequestStatus::Assigned);
            }

            return $workOrder;
        });
    }
}

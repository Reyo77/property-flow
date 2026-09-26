<?php

namespace App\Http\Resources\Api\V1;

use App\Models\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkOrder
 */
class WorkOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'community_id' => $this->community_id,
            'service_request_id' => $this->service_request_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'assignee' => match (true) {
                $this->assignedVendor !== null => ['type' => 'vendor', 'id' => $this->assignedVendor->id, 'name' => $this->assignedVendor->name],
                $this->assignedUser !== null => ['type' => 'staff', 'id' => $this->assignedUser->id, 'name' => $this->assignedUser->name],
                default => null,
            },
            'due_on' => $this->due_on?->toDateString(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'completion_notes' => $this->completion_notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

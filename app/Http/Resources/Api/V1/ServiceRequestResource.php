<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceRequest
 */
class ServiceRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'community_id' => $this->community_id,
            'unit' => $this->unit === null ? null : ['id' => $this->unit->id, 'label' => $this->unit->label()],
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category->value,
            'priority' => $this->priority->value,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'entry_permission' => $this->entry_permission,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'comments' => ServiceRequestCommentResource::collection($this->whenLoaded('comments')),
        ];
    }
}

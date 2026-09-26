<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ArchitecturalRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ArchitecturalRequest
 */
class ArchitecturalRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'community_id' => $this->community_id,
            'unit' => ['id' => $this->unit->id, 'label' => $this->unit->label()],
            'title' => $this->title,
            'description' => $this->description,
            'contractor' => $this->contractor,
            'planned_start_on' => $this->planned_start_on?->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'conditions' => $this->conditions,
            'decision_notes' => $this->decision_notes,
            'decided_at' => $this->decided_at?->toIso8601String(),
            'decision_letter_url' => $this->decided_at === null ? null : route('api.v1.communities.architectural-requests.letter', [$this->community_id, $this->id]),
            'submitted_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

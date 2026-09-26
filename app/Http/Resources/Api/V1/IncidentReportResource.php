<?php

namespace App\Http\Resources\Api\V1;

use App\Models\IncidentReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin IncidentReport
 */
class IncidentReportResource extends JsonResource
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
            'location' => $this->location,
            'severity' => $this->severity->value,
            'occurred_at' => $this->occurred_at->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'resolution_notes' => $this->resolution_notes,
        ];
    }
}

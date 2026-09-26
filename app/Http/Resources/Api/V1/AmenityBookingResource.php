<?php

namespace App\Http\Resources\Api\V1;

use App\Models\AmenityBooking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AmenityBooking
 */
class AmenityBookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'community_id' => $this->community_id,
            'amenity' => ['id' => $this->amenity->id, 'name' => $this->amenity->name],
            'unit' => $this->unit === null ? null : ['id' => $this->unit->id, 'label' => $this->unit->label()],
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at->toIso8601String(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'fee_cents' => $this->fee_cents,
            'deposit_cents' => $this->deposit_cents,
            'notes' => $this->notes,
            'decision_notes' => $this->decision_notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

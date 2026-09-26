<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Package
 */
class PackageResource extends JsonResource
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
            'recipient' => $this->resident === null ? null : ['id' => $this->resident->id, 'name' => $this->resident->name],
            'carrier' => $this->carrier,
            'tracking_number' => $this->tracking_number,
            'shelf_location' => $this->shelf_location,
            'status' => $this->status->value,
            'arrived_at' => $this->created_at?->toIso8601String(),
            'released_at' => $this->released_at?->toIso8601String(),
            'released_to_name' => $this->released_to_name,
        ];
    }
}

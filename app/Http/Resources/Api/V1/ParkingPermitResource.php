<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ParkingPermit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ParkingPermit
 */
class ParkingPermitResource extends JsonResource
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
            'plate_number' => $this->plate_number,
            'visitor_name' => $this->visitor_name,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on->toDateString(),
            'notes' => $this->notes,
        ];
    }
}

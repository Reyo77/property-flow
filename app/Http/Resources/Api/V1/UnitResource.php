<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Unit
 */
class UnitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'community_id' => $this->community_id,
            'building' => $this->building === null ? null : ['id' => $this->building->id, 'name' => $this->building->name],
            'number' => $this->number,
            'label' => $this->label(),
            'floor' => $this->floor,
            'area' => $this->area,
            'unit_factor' => $this->unit_factor,
            'parking' => $this->parking,
            'locker' => $this->locker,
        ];
    }
}

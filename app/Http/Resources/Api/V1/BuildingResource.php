<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Building;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Building
 */
class BuildingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'community_id' => $this->community_id,
            'name' => $this->name,
            'address' => $this->address,
            'floors' => $this->floors,
        ];
    }
}

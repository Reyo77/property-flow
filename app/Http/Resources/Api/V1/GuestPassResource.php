<?php

namespace App\Http\Resources\Api\V1;

use App\Models\GuestPass;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GuestPass
 */
class GuestPassResource extends JsonResource
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
            'guest_name' => $this->guest_name,
            'code' => $this->code,
            'valid_from' => $this->valid_from->toIso8601String(),
            'valid_until' => $this->valid_until->toIso8601String(),
            'used_at' => $this->used_at?->toIso8601String(),
        ];
    }
}

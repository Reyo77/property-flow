<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Residency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Residency
 */
class ResidencyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unit' => ['id' => $this->unit->id, 'label' => $this->unit->label()],
            'type' => $this->type->value,
            'is_primary' => $this->is_primary,
            'moved_in_on' => $this->moved_in_on?->toDateString(),
            'moved_out_on' => $this->moved_out_on?->toDateString(),
        ];
    }
}

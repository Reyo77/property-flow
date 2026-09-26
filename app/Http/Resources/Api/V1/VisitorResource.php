<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Visitor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Visitor
 */
class VisitorResource extends JsonResource
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
            'visitor_name' => $this->visitor_name,
            'purpose' => $this->purpose,
            'notes' => $this->notes,
            'checked_in_at' => $this->checked_in_at->toIso8601String(),
            'checked_out_at' => $this->checked_out_at?->toIso8601String(),
        ];
    }
}

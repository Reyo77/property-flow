<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Resident;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Resident
 */
class ResidentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'has_login' => $this->user_id !== null,
            'residencies' => ResidencyResource::collection($this->whenLoaded('residencies')),
        ];
    }
}

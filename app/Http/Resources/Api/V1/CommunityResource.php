<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Community;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Community
 */
class CommunityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'address' => [
                'line_1' => $this->address_line_1,
                'line_2' => $this->address_line_2,
                'city' => $this->city,
                'region' => $this->region,
                'postal_code' => $this->postal_code,
                'country' => $this->country,
            ],
            'timezone' => $this->timezone,
            'currency' => $this->currency,
        ];
    }
}

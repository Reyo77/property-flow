<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\RsvpStatus;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Event
 */
class EventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'community_id' => $this->community_id,
            'title' => $this->title,
            'description' => $this->description,
            'location' => $this->location,
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at->toIso8601String(),
            'rsvps' => $this->whenLoaded('rsvps', fn () => [
                'going' => $this->rsvps->where('status', RsvpStatus::Going)->count(),
                'maybe' => $this->rsvps->where('status', RsvpStatus::Maybe)->count(),
                'not_going' => $this->rsvps->where('status', RsvpStatus::NotGoing)->count(),
                'mine' => $this->rsvps->firstWhere('user_id', $request->user()?->getAuthIdentifier())?->status->value,
            ]),
        ];
    }
}

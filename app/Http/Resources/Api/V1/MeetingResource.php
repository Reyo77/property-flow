<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Meeting;
use App\Models\MeetingAgendaItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Meeting
 */
class MeetingResource extends JsonResource
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
            'kind' => $this->kind->value,
            'kind_label' => $this->kind->label(),
            'starts_at' => $this->starts_at->toIso8601String(),
            'location' => $this->location,
            'description' => $this->description,
            'closed' => $this->isClosed(),
            'agenda' => $this->whenLoaded('agendaItems', fn () => $this->agendaItems->map(fn (MeetingAgendaItem $item) => $item->title)->values()),
            'minutes' => $this->when($request->user()?->can('viewMinutes', $this->resource) === true, fn () => [
                'text' => $this->minutes,
                'published_at' => $this->minutes_published_at?->toIso8601String(),
            ]),
        ];
    }
}

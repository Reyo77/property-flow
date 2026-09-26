<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Announcement
 */
class AnnouncementResource extends JsonResource
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
            'body' => $this->body,
            'pinned' => $this->pinned,
            'audience' => $this->audience_type->value,
            'published_at' => $this->published_at?->toIso8601String(),
            'publish_at' => $this->when($this->published_at === null, fn () => $this->publish_at?->toIso8601String()),
            'author' => $this->whenLoaded('createdBy', fn () => $this->createdBy === null ? null : ['id' => $this->createdBy->id, 'name' => $this->createdBy->name]),
        ];
    }
}

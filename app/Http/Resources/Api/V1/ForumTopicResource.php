<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ForumTopic;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ForumTopic
 */
class ForumTopicResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'community_id' => $this->community_id,
            'kind' => $this->kind->value,
            'title' => $this->title,
            'body' => $this->body,
            'price_cents' => $this->price_cents,
            'author' => ['id' => $this->author->id, 'name' => $this->author->name],
            'pinned' => $this->is_pinned,
            'locked' => $this->isLocked(),
            'closed' => $this->closed_at !== null,
            'hidden' => $this->isHidden(),
            'last_activity_at' => $this->last_activity_at->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'replies' => ForumPostResource::collection($this->whenLoaded('posts')),
        ];
    }
}

<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ForumPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ForumPost
 */
class ForumPostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'author' => ['id' => $this->author->id, 'name' => $this->author->name],
            'hidden' => $this->hidden_at !== null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

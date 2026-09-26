<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ServiceRequestComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceRequestComment
 */
class ServiceRequestCommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'internal' => ! $this->visible_to_resident,
            'author' => $this->author === null ? null : ['id' => $this->author->id, 'name' => $this->author->name],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

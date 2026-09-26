<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Document
 */
class DocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $version = $this->currentVersion;

        return [
            'id' => $this->id,
            'community_id' => $this->community_id,
            'folder_id' => $this->folder_id,
            'title' => $this->title,
            'visibility' => $this->visibility->value,
            'file' => $version === null ? null : [
                'name' => $version->original_filename,
                'mime_type' => $version->mime_type,
                'size_bytes' => $version->size_bytes,
                'version' => $version->version_number,
                'download_url' => route('api.v1.communities.documents.download', [$this->community_id, $this->id]),
            ],
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

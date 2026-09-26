<?php

namespace App\Http\Resources\Api\V1;

use App\Models\DocumentFolder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DocumentFolder
 */
class DocumentFolderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'visibility' => $this->visibility->value,
        ];
    }
}

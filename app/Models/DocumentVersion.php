<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\DocumentVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

/**
 * One uploaded file for a document. Kept even after a newer version replaces it as the current one.
 *
 * @property int $id
 * @property int $document_id
 * @property int|null $uploaded_by_id
 * @property int $version_number
 * @property string $disk_path
 * @property string $original_filename
 * @property string $mime_type
 * @property int $size_bytes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Document $document
 * @property-read User|null $uploadedBy
 */
#[Fillable(['uploaded_by_id', 'version_number', 'disk_path', 'original_filename', 'mime_type', 'size_bytes'])]
class DocumentVersion extends Model
{
    /** @use HasFactory<DocumentVersionFactory> */
    use BelongsToCompany, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    public function humanSize(): string
    {
        return Number::fileSize($this->size_bytes, precision: 1);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

/**
 * A photo or file attached to a service request (and, later, other records).
 *
 * @property int $id
 * @property string $attachable_type
 * @property int $attachable_id
 * @property int|null $uploaded_by_id
 * @property string $disk_path
 * @property string $original_filename
 * @property string $mime_type
 * @property int $size_bytes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model $attachable
 * @property-read User|null $uploadedBy
 */
#[Fillable(['disk_path', 'original_filename', 'mime_type', 'size_bytes', 'uploaded_by_id'])]
class Attachment extends Model
{
    /** @use HasFactory<AttachmentFactory> */
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
     * @return MorphTo<Model, $this>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function humanSize(): string
    {
        return Number::fileSize($this->size_bytes, precision: 1);
    }
}

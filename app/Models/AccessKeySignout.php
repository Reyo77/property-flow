<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\AccessKeySignoutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $access_key_id
 * @property string $signed_out_to
 * @property string|null $signed_out_to_phone
 * @property int|null $signed_out_by_id
 * @property Carbon $signed_out_at
 * @property Carbon|null $due_back_at
 * @property Carbon|null $returned_at
 * @property int|null $returned_to_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AccessKey $accessKey
 * @property-read User|null $signedOutBy
 * @property-read User|null $returnedTo
 */
#[Fillable(['signed_out_to', 'signed_out_to_phone', 'due_back_at'])]
class AccessKeySignout extends Model
{
    /** @use HasFactory<AccessKeySignoutFactory> */
    use BelongsToCompany, HasFactory, LogsActivity;

    protected static function booted(): void
    {
        static::creating(function (AccessKeySignout $signout): void {
            if ($signout->getAttribute('signed_out_at') === null) {
                $signout->forceFill(['signed_out_at' => now()]);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'signed_out_at' => 'datetime',
            'due_back_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AccessKey, $this>
     */
    public function accessKey(): BelongsTo
    {
        return $this->belongsTo(AccessKey::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function signedOutBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_out_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function returnedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_to_id');
    }

    public function isOverdue(): bool
    {
        return $this->returned_at === null && $this->due_back_at !== null && $this->due_back_at->isPast();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['returned_at'])->logOnlyDirty();
    }
}

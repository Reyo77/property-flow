<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\PatrolScanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $patrol_checkpoint_id
 * @property int|null $scanned_by_id
 * @property Carbon $scanned_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PatrolCheckpoint $patrolCheckpoint
 * @property-read User|null $scannedBy
 */
#[Fillable([])]
class PatrolScan extends Model
{
    /** @use HasFactory<PatrolScanFactory> */
    use BelongsToCompany, HasFactory, LogsActivity;

    protected static function booted(): void
    {
        static::creating(function (PatrolScan $scan): void {
            if ($scan->getAttribute('scanned_at') === null) {
                $scan->forceFill(['scanned_at' => now()]);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PatrolCheckpoint, $this>
     */
    public function patrolCheckpoint(): BelongsTo
    {
        return $this->belongsTo(PatrolCheckpoint::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function scannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['scanned_at'])->logOnlyDirty();
    }
}

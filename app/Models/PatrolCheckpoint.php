<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\PatrolCheckpointFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $patrol_route_id
 * @property string $name
 * @property int $position
 * @property string $qr_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PatrolRoute $patrolRoute
 * @property-read HasMany<PatrolScan, $this> $scans
 */
#[Fillable(['name', 'position'])]
class PatrolCheckpoint extends Model
{
    /** @use HasFactory<PatrolCheckpointFactory> */
    use BelongsToCompany, HasFactory, LogsActivity;

    protected static function booted(): void
    {
        static::creating(function (PatrolCheckpoint $checkpoint): void {
            if ($checkpoint->getAttribute('qr_token') === null) {
                $checkpoint->forceFill(['qr_token' => Str::random(32)]);
            }
        });
    }

    /**
     * @return BelongsTo<PatrolRoute, $this>
     */
    public function patrolRoute(): BelongsTo
    {
        return $this->belongsTo(PatrolRoute::class);
    }

    /**
     * @return HasMany<PatrolScan, $this>
     */
    public function scans(): HasMany
    {
        return $this->hasMany(PatrolScan::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCommunity;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Contracts\BelongsToOneCommunity;
use Carbon\CarbonInterface;
use Database\Factories\PatrolRouteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $community_id
 * @property string $name
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Community $community
 */
#[Fillable(['name', 'active'])]
class PatrolRoute extends Model implements BelongsToOneCommunity
{
    /** @use HasFactory<PatrolRouteFactory> */
    use BelongsToCommunity, BelongsToCompany, HasFactory, LogsActivity, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Community, $this>
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * @return HasMany<PatrolCheckpoint, $this>
     */
    public function checkpoints(): HasMany
    {
        return $this->hasMany(PatrolCheckpoint::class)->orderBy('position');
    }

    /**
     * Each checkpoint on this route with its most recent scan on the given local date, so a
     * missed-checkpoint report can show which ones were skipped that day.
     *
     * @return list<array{checkpoint: PatrolCheckpoint, last_scan_at: Carbon|null}>
     */
    public function scanSummaryFor(CarbonInterface $date): array
    {
        $dayStart = $date->clone()->startOfDay();
        $dayEnd = $date->clone()->endOfDay();

        $checkpoints = $this->checkpoints()
            ->with(['scans' => fn ($query) => $query->whereBetween('scanned_at', [$dayStart, $dayEnd])->latest('scanned_at')])
            ->get();

        return array_values($checkpoints->map(fn (PatrolCheckpoint $checkpoint) => [
            'checkpoint' => $checkpoint,
            'last_scan_at' => $checkpoint->scans->first()?->scanned_at,
        ])->all());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}

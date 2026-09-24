<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCommunity;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Contracts\BelongsToOneCommunity;
use Database\Factories\ParkingPermitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $community_id
 * @property int $unit_id
 * @property string $plate_number
 * @property string|null $visitor_name
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property int|null $issued_by_id
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Community $community
 * @property-read Unit $unit
 * @property-read User|null $issuedBy
 */
#[Fillable(['unit_id', 'plate_number', 'visitor_name', 'starts_on', 'ends_on', 'notes'])]
class ParkingPermit extends Model implements BelongsToOneCommunity
{
    /** @use HasFactory<ParkingPermitFactory> */
    use BelongsToCommunity, BelongsToCompany, HasFactory, LogsActivity, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
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
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_id');
    }

    public function isActive(): bool
    {
        $today = now()->toDateString();

        return $this->starts_on->toDateString() <= $today && $this->ends_on->toDateString() >= $today;
    }

    /**
     * @param  Builder<ParkingPermit>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $today = now()->toDateString();
        $query->where('starts_on', '<=', $today)->where('ends_on', '>=', $today);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}

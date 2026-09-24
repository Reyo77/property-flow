<?php

namespace App\Models;

use App\Enums\PackageStatus;
use App\Models\Concerns\BelongsToCommunity;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Contracts\BelongsToOneCommunity;
use Database\Factories\PackageFactory;
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
 * @property int|null $unit_id
 * @property int|null $resident_id
 * @property string $carrier
 * @property string|null $tracking_number
 * @property string|null $shelf_location
 * @property PackageStatus $status
 * @property int|null $logged_by_id
 * @property Carbon|null $notified_at
 * @property Carbon|null $released_at
 * @property int|null $released_by_id
 * @property string|null $released_to_name
 * @property string|null $signature_disk_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Community $community
 * @property-read Unit|null $unit
 * @property-read Resident|null $resident
 * @property-read User|null $loggedBy
 * @property-read User|null $releasedBy
 */
#[Fillable(['unit_id', 'resident_id', 'carrier', 'tracking_number', 'shelf_location'])]
class Package extends Model implements BelongsToOneCommunity
{
    /** @use HasFactory<PackageFactory> */
    use BelongsToCommunity, BelongsToCompany, HasFactory, LogsActivity, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Package $package): void {
            $package->status ??= PackageStatus::AwaitingPickup;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PackageStatus::class,
            'notified_at' => 'datetime',
            'released_at' => 'datetime',
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
     * @return BelongsTo<Resident, $this>
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function loggedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by_id');
    }

    /**
     * @param  Builder<Package>  $query
     */
    #[Scope]
    protected function awaitingPickup(Builder $query): void
    {
        $query->where('status', PackageStatus::AwaitingPickup);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'shelf_location'])->logOnlyDirty();
    }
}

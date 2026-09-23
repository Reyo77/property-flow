<?php

namespace App\Models;

use App\Enums\Assignee;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\MaintenanceScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A recurring maintenance job for an asset. The scheduler creates a work order each time
 * `next_due_on` arrives, then advances it by `interval_days`.
 *
 * @property int $id
 * @property int $asset_id
 * @property string $title
 * @property int $interval_days
 * @property Carbon $next_due_on
 * @property Carbon|null $last_generated_on
 * @property Assignee|null $assignee_type
 * @property int|null $assigned_user_id
 * @property int|null $assigned_vendor_id
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Asset $asset
 * @property-read User|null $assignedUser
 * @property-read Vendor|null $assignedVendor
 */
#[Fillable(['title', 'interval_days', 'next_due_on', 'assignee_type', 'assigned_user_id', 'assigned_vendor_id', 'active'])]
class MaintenanceSchedule extends Model
{
    /** @use HasFactory<MaintenanceScheduleFactory> */
    use BelongsToCompany, HasFactory, LogsActivity, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'interval_days' => 'integer',
            'next_due_on' => 'date',
            'last_generated_on' => 'date',
            'assignee_type' => Assignee::class,
            'active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function assignedVendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'assigned_vendor_id');
    }

    /**
     * @return HasMany<WorkOrder, $this>
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /**
     * @param  Builder<MaintenanceSchedule>  $query
     */
    #[Scope]
    protected function dueToGenerate(Builder $query): void
    {
        $query->where('active', true)->where('next_due_on', '<=', now()->toDateString());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}

<?php

namespace App\Models;

use App\Enums\Assignee;
use App\Enums\WorkOrderStatus;
use App\Models\Concerns\BelongsToCommunity;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Contracts\BelongsToOneCommunity;
use Database\Factories\WorkOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use LogicException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $community_id
 * @property int|null $service_request_id
 * @property int|null $maintenance_schedule_id
 * @property int|null $asset_id
 * @property int|null $created_by_id
 * @property string $title
 * @property string|null $description
 * @property Assignee|null $assignee_type
 * @property int|null $assigned_user_id
 * @property int|null $assigned_vendor_id
 * @property WorkOrderStatus $status
 * @property Carbon|null $due_on
 * @property Carbon|null $completed_at
 * @property string|null $completion_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Community $community
 * @property-read ServiceRequest|null $serviceRequest
 * @property-read MaintenanceSchedule|null $maintenanceSchedule
 * @property-read Asset|null $asset
 * @property-read User|null $createdBy
 * @property-read User|null $assignedUser
 * @property-read Vendor|null $assignedVendor
 */
#[Fillable(['title', 'description', 'assignee_type', 'assigned_user_id', 'assigned_vendor_id', 'due_on', 'completion_notes'])]
class WorkOrder extends Model implements BelongsToOneCommunity
{
    /** @use HasFactory<WorkOrderFactory> */
    use BelongsToCommunity, BelongsToCompany, HasFactory, LogsActivity, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (WorkOrder $workOrder): void {
            $workOrder->status ??= WorkOrderStatus::Pending;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assignee_type' => Assignee::class,
            'status' => WorkOrderStatus::class,
            'due_on' => 'date',
            'completed_at' => 'datetime',
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
     * @return BelongsTo<ServiceRequest, $this>
     */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    /**
     * @return BelongsTo<MaintenanceSchedule, $this>
     */
    public function maintenanceSchedule(): BelongsTo
    {
        return $this->belongsTo(MaintenanceSchedule::class);
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
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
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

    public function isAssignedToVendorUser(User $user): bool
    {
        return $this->assignee_type === Assignee::Vendor
            && $user->vendor !== null
            && $this->assigned_vendor_id === $user->vendor->id;
    }

    /**
     * Move the work order to a new status, refusing any transition the state machine forbids.
     *
     * @throws LogicException
     */
    public function transitionTo(WorkOrderStatus $target): void
    {
        if (! $this->status->canTransitionTo($target)) {
            throw new LogicException("Cannot move a work order from {$this->status->value} to {$target->value}.");
        }

        $attributes = ['status' => $target];

        if ($target === WorkOrderStatus::Completed) {
            $attributes['completed_at'] = now();
        }

        $this->forceFill($attributes)->save();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'assignee_type', 'assigned_user_id', 'assigned_vendor_id'])->logOnlyDirty();
    }
}

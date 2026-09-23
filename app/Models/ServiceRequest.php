<?php

namespace App\Models;

use App\Enums\ServiceRequestCategory;
use App\Enums\ServiceRequestPriority;
use App\Enums\ServiceRequestStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Concerns\BelongsToCommunity;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Contracts\BelongsToOneCommunity;
use Database\Factories\ServiceRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use LogicException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $community_id
 * @property int|null $unit_id
 * @property int|null $reported_by_resident_id
 * @property int|null $reported_by_user_id
 * @property string $title
 * @property string $description
 * @property ServiceRequestCategory $category
 * @property ServiceRequestPriority $priority
 * @property ServiceRequestStatus $status
 * @property bool $entry_permission
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Community $community
 * @property-read Unit|null $unit
 * @property-read Resident|null $reportedByResident
 * @property-read User|null $reportedByUser
 * @property-read Collection<int, WorkOrder> $workOrders
 */
#[Fillable(['unit_id', 'title', 'description', 'category', 'priority', 'entry_permission'])]
class ServiceRequest extends Model implements BelongsToOneCommunity
{
    /** @use HasFactory<ServiceRequestFactory> */
    use BelongsToCommunity, BelongsToCompany, HasFactory, LogsActivity, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (ServiceRequest $serviceRequest): void {
            $serviceRequest->status ??= ServiceRequestStatus::Open;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ServiceRequestCategory::class,
            'priority' => ServiceRequestPriority::class,
            'status' => ServiceRequestStatus::class,
            'entry_permission' => 'boolean',
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
    public function reportedByResident(): BelongsTo
    {
        return $this->belongsTo(Resident::class, 'reported_by_resident_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reportedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    /**
     * @return HasMany<ServiceRequestComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(ServiceRequestComment::class);
    }

    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * @return HasMany<WorkOrder, $this>
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /**
     * The work order actively being worked, if any (a cancelled one does not block a new one).
     */
    public function currentWorkOrder(): ?WorkOrder
    {
        return $this->workOrders()->whereNot('status', WorkOrderStatus::Cancelled)->latest('id')->first();
    }

    /**
     * Move the request to a new status, refusing any transition the state machine forbids.
     *
     * @throws LogicException
     */
    public function transitionTo(ServiceRequestStatus $target): void
    {
        if (! $this->status->canTransitionTo($target)) {
            throw new LogicException("Cannot move a service request from {$this->status->value} to {$target->value}.");
        }

        $this->forceFill(['status' => $target])->save();
    }

    /**
     * @param  Builder<ServiceRequest>  $query
     */
    #[Scope]
    protected function openStatus(Builder $query): void
    {
        $query->whereNot('status', ServiceRequestStatus::Closed);
    }

    /**
     * Whether the request has been open longer than its priority's SLA allows.
     */
    public function isOverdue(): bool
    {
        return $this->status->isOpen()
            && $this->status !== ServiceRequestStatus::Resolved
            && $this->created_at !== null
            && $this->created_at->addHours($this->priority->slaHours())->isPast();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status'])->logOnlyDirty();
    }
}

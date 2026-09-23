<?php

namespace App\Models;

use App\Enums\TaskStatus;
use App\Models\Concerns\BelongsToCommunity;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Contracts\BelongsToOneCommunity;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A general to-do, not tied to a resident's service request.
 *
 * @property int $id
 * @property int $community_id
 * @property int|null $assigned_to_id
 * @property int|null $created_by_id
 * @property string $title
 * @property string|null $description
 * @property Carbon|null $due_on
 * @property TaskStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Community $community
 * @property-read User|null $assignedTo
 * @property-read User|null $createdBy
 */
#[Fillable(['title', 'description', 'due_on', 'assigned_to_id'])]
class Task extends Model implements BelongsToOneCommunity
{
    /** @use HasFactory<TaskFactory> */
    use BelongsToCommunity, BelongsToCompany, HasFactory, LogsActivity, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Task $task): void {
            $task->status ??= TaskStatus::Open;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'status' => TaskStatus::class,
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
     * @return BelongsTo<User, $this>
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function isOverdue(): bool
    {
        return $this->status === TaskStatus::Open && $this->due_on !== null && $this->due_on->isPast();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}

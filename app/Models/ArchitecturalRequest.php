<?php

namespace App\Models;

use App\Enums\ArchitecturalRequestStatus;
use App\Models\Concerns\BelongsToCommunity;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Contracts\BelongsToOneCommunity;
use Database\Factories\ArchitecturalRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * An owner asking the board's permission for a change to their unit or its exterior
 * (renovation, flooring, a new window, a fence), with plans attached.
 *
 * @property int $id
 * @property int $company_id
 * @property int $community_id
 * @property int $unit_id
 * @property int|null $submitted_by_id
 * @property string $title
 * @property string $description
 * @property string|null $contractor
 * @property Carbon|null $planned_start_on
 * @property ArchitecturalRequestStatus $status
 * @property string|null $conditions
 * @property string|null $decision_notes
 * @property int|null $decided_by_id
 * @property Carbon|null $decided_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Community $community
 * @property-read Unit $unit
 * @property-read User|null $submittedBy
 * @property-read User|null $decidedBy
 */
#[Fillable(['unit_id', 'title', 'description', 'contractor', 'planned_start_on'])]
class ArchitecturalRequest extends Model implements BelongsToOneCommunity
{
    /** @use HasFactory<ArchitecturalRequestFactory> */
    use BelongsToCommunity, BelongsToCompany, HasFactory, LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ArchitecturalRequestStatus::class,
            'planned_start_on' => 'date',
            'decided_at' => 'datetime',
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
        return $this->belongsTo(Unit::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_id');
    }

    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function isSubmittedBy(User $user): bool
    {
        return $this->submitted_by_id === $user->id;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'conditions', 'decided_by_id'])->logOnlyDirty();
    }
}

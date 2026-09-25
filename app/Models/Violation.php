<?php

namespace App\Models;

use App\Enums\ViolationStage;
use App\Enums\ViolationStatus;
use App\Models\Concerns\BelongsToCommunity;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Contracts\BelongsToOneCommunity;
use Database\Factories\ViolationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A breach of a rule at a unit, working its way up the escalation ladder until it's resolved.
 *
 * @property int $id
 * @property int $company_id
 * @property int $community_id
 * @property int $violation_rule_id
 * @property int $unit_id
 * @property int|null $reported_by_id
 * @property Carbon $observed_at
 * @property string|null $location
 * @property string $description
 * @property ViolationStatus $status
 * @property ViolationStage|null $stage
 * @property int $fines_issued
 * @property Carbon|null $next_action_on
 * @property Carbon|null $closed_at
 * @property string|null $resolution_notes
 * @property int|null $closed_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Community $community
 * @property-read ViolationRule $rule
 * @property-read Unit $unit
 * @property-read User|null $reportedBy
 */
class Violation extends Model implements BelongsToOneCommunity
{
    /** @use HasFactory<ViolationFactory> */
    use BelongsToCommunity, BelongsToCompany, HasFactory, LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ViolationStatus::class,
            'stage' => ViolationStage::class,
            'observed_at' => 'datetime',
            'next_action_on' => 'date',
            'closed_at' => 'datetime',
            'fines_issued' => 'integer',
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
     * @return BelongsTo<ViolationRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(ViolationRule::class, 'violation_rule_id');
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
    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_id');
    }

    /**
     * @return HasMany<ViolationNotice, $this>
     */
    public function notices(): HasMany
    {
        return $this->hasMany(ViolationNotice::class)->orderBy('issued_on')->orderBy('id');
    }

    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function isOpen(): bool
    {
        return $this->status === ViolationStatus::Open;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'stage', 'fines_issued', 'next_action_on'])->logOnlyDirty();
    }
}

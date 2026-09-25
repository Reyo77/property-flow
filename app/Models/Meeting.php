<?php

namespace App\Models;

use App\Enums\MeetingKind;
use App\Enums\VotingWeighting;
use App\Models\Concerns\BelongsToCommunity;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Contracts\BelongsToOneCommunity;
use Database\Factories\MeetingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * An owners' or board meeting: agenda, attendance (and so quorum), minutes, and the ballots
 * voted on at it.
 *
 * @property int $id
 * @property int $company_id
 * @property int $community_id
 * @property string $title
 * @property MeetingKind $kind
 * @property Carbon $starts_at
 * @property string|null $location
 * @property string|null $description
 * @property VotingWeighting $weighting
 * @property int $quorum_percent
 * @property string|null $minutes
 * @property Carbon|null $minutes_published_at
 * @property Carbon|null $closed_at
 * @property int|null $created_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Community $community
 */
#[Fillable(['title', 'kind', 'starts_at', 'location', 'description', 'weighting', 'quorum_percent'])]
class Meeting extends Model implements BelongsToOneCommunity
{
    /** @use HasFactory<MeetingFactory> */
    use BelongsToCommunity, BelongsToCompany, HasFactory, LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => MeetingKind::class,
            'weighting' => VotingWeighting::class,
            'starts_at' => 'datetime',
            'quorum_percent' => 'integer',
            'minutes_published_at' => 'datetime',
            'closed_at' => 'datetime',
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
     * @return HasMany<MeetingAgendaItem, $this>
     */
    public function agendaItems(): HasMany
    {
        return $this->hasMany(MeetingAgendaItem::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<MeetingAttendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(MeetingAttendance::class);
    }

    /**
     * @return HasMany<Ballot, $this>
     */
    public function ballots(): HasMany
    {
        return $this->hasMany(Ballot::class);
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    public function hasPublishedMinutes(): bool
    {
        return $this->minutes_published_at !== null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}

<?php

namespace App\Models;

use App\Enums\BallotStatus;
use App\Enums\VotingWeighting;
use App\Models\Concerns\BelongsToCommunity;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Contracts\BelongsToOneCommunity;
use Carbon\CarbonInterface;
use Database\Factories\BallotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * An owners' vote: one or more questions, open between two times, counted per unit (equally or
 * by unit factor). Once closed, the tally is frozen into `results` and never recomputed.
 *
 * @property int $id
 * @property int $company_id
 * @property int $community_id
 * @property int|null $meeting_id
 * @property string $title
 * @property string|null $description
 * @property VotingWeighting $weighting
 * @property int $quorum_percent
 * @property Carbon $opens_at
 * @property Carbon $closes_at
 * @property Carbon|null $published_at
 * @property Carbon|null $closed_at
 * @property array{eligible_units: int, eligible_weight: string, voted_units: int, voted_weight: string, turnout_percent: string, quorum_met: bool, questions: list<array{id: int, title: string, options: list<array{id: int, label: string, votes: int, weight: string, percent: string}>}>}|null $results
 * @property int|null $created_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Community $community
 * @property-read Meeting|null $meeting
 */
#[Fillable(['meeting_id', 'title', 'description', 'weighting', 'quorum_percent', 'opens_at', 'closes_at'])]
class Ballot extends Model implements BelongsToOneCommunity
{
    /** @use HasFactory<BallotFactory> */
    use BelongsToCommunity, BelongsToCompany, HasFactory, LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weighting' => VotingWeighting::class,
            'quorum_percent' => 'integer',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
            'results' => 'array',
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
     * @return BelongsTo<Meeting, $this>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /**
     * @return HasMany<BallotQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(BallotQuestion::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<BallotVote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(BallotVote::class);
    }

    /**
     * @return HasMany<BallotProxy, $this>
     */
    public function proxies(): HasMany
    {
        return $this->hasMany(BallotProxy::class);
    }

    public function status(?CarbonInterface $at = null): BallotStatus
    {
        $at ??= now();

        return match (true) {
            $this->closed_at !== null => BallotStatus::Closed,
            $this->published_at === null => BallotStatus::Draft,
            $at->lessThan($this->opens_at) => BallotStatus::Upcoming,
            $at->lessThan($this->closes_at) => BallotStatus::Open,
            default => BallotStatus::Ended,
        };
    }

    public function isOpen(?CarbonInterface $at = null): bool
    {
        return $this->status($at) === BallotStatus::Open;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('ballots')->logOnly(['title', 'weighting', 'quorum_percent', 'opens_at', 'closes_at', 'published_at', 'closed_at'])->logOnlyDirty();
    }
}

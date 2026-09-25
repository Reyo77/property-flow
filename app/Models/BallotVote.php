<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\IsAppendOnly;
use Database\Factories\BallotVoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A unit's vote on a ballot. Votes are final: they can't be changed or withdrawn.
 *
 * @property int $id
 * @property int $company_id
 * @property int $ballot_id
 * @property int $unit_id
 * @property int $cast_by_id
 * @property int|null $ballot_proxy_id
 * @property string $weight
 * @property Carbon $cast_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Ballot $ballot
 * @property-read Unit $unit
 * @property-read User $castBy
 * @property-read BallotProxy|null $proxy
 */
class BallotVote extends Model
{
    /** @use HasFactory<BallotVoteFactory> */
    use BelongsToCompany, HasFactory, IsAppendOnly;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight' => 'decimal:6',
            'cast_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Ballot, $this>
     */
    public function ballot(): BelongsTo
    {
        return $this->belongsTo(Ballot::class);
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
    public function castBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cast_by_id');
    }

    /**
     * @return BelongsTo<BallotProxy, $this>
     */
    public function proxy(): BelongsTo
    {
        return $this->belongsTo(BallotProxy::class, 'ballot_proxy_id');
    }

    /**
     * @return HasMany<BallotAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(BallotAnswer::class);
    }
}

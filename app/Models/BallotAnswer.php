<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\IsAppendOnly;
use Database\Factories\BallotAnswerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int $ballot_vote_id
 * @property int $ballot_question_id
 * @property int $ballot_option_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BallotVote $vote
 * @property-read BallotOption $option
 */
class BallotAnswer extends Model
{
    /** @use HasFactory<BallotAnswerFactory> */
    use BelongsToCompany, HasFactory, IsAppendOnly;

    /**
     * @return BelongsTo<BallotVote, $this>
     */
    public function vote(): BelongsTo
    {
        return $this->belongsTo(BallotVote::class, 'ballot_vote_id');
    }

    /**
     * @return BelongsTo<BallotOption, $this>
     */
    public function option(): BelongsTo
    {
        return $this->belongsTo(BallotOption::class, 'ballot_option_id');
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\BallotOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int $ballot_question_id
 * @property int $position
 * @property string $label
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BallotQuestion $question
 */
#[Fillable(['position', 'label'])]
class BallotOption extends Model
{
    /** @use HasFactory<BallotOptionFactory> */
    use BelongsToCompany, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    /**
     * @return BelongsTo<BallotQuestion, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(BallotQuestion::class, 'ballot_question_id');
    }
}

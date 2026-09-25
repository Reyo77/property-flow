<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\BallotQuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int $ballot_id
 * @property int $position
 * @property string $title
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Ballot $ballot
 */
#[Fillable(['position', 'title', 'description'])]
class BallotQuestion extends Model
{
    /** @use HasFactory<BallotQuestionFactory> */
    use BelongsToCompany, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    /**
     * @return BelongsTo<Ballot, $this>
     */
    public function ballot(): BelongsTo
    {
        return $this->belongsTo(Ballot::class);
    }

    /**
     * @return HasMany<BallotOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(BallotOption::class)->orderBy('position')->orderBy('id');
    }
}

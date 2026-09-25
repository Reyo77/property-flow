<?php

namespace App\Models;

use App\Enums\SurveyQuestionKind;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\SurveyQuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int $survey_id
 * @property int $position
 * @property SurveyQuestionKind $kind
 * @property string $title
 * @property bool $is_required
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Survey $survey
 */
#[Fillable(['position', 'kind', 'title', 'is_required'])]
class SurveyQuestion extends Model
{
    /** @use HasFactory<SurveyQuestionFactory> */
    use BelongsToCompany, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => SurveyQuestionKind::class, 'is_required' => 'boolean', 'position' => 'integer'];
    }

    /**
     * @return BelongsTo<Survey, $this>
     */
    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    /**
     * @return HasMany<SurveyOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(SurveyOption::class)->orderBy('position')->orderBy('id');
    }
}

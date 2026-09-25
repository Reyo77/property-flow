<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\SurveyAnswerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One answer: a chosen option (a multiple-choice answer is several rows), a rating, or text.
 *
 * @property int $id
 * @property int $company_id
 * @property int $survey_response_id
 * @property int $survey_question_id
 * @property int|null $survey_option_id
 * @property int|null $rating
 * @property string|null $text
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SurveyAnswer extends Model
{
    /** @use HasFactory<SurveyAnswerFactory> */
    use BelongsToCompany, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['rating' => 'integer'];
    }
}

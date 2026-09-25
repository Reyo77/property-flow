<?php

namespace Database\Factories;

use App\Enums\SurveyQuestionKind;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SurveyQuestion>
 */
class SurveyQuestionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'survey_id' => Survey::factory(),
            'company_id' => fn (array $attributes) => Survey::withoutGlobalScopes()->whereKey($attributes['survey_id'])->valueOrFail('company_id'),
            'position' => 1,
            'kind' => SurveyQuestionKind::SingleChoice,
            'title' => 'How satisfied are you?',
            'is_required' => true,
        ];
    }
}

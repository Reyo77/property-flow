<?php

namespace Database\Factories;

use App\Models\SurveyAnswer;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SurveyAnswer>
 */
class SurveyAnswerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'survey_response_id' => SurveyResponse::factory(),
            'company_id' => fn (array $attributes) => SurveyResponse::withoutGlobalScopes()->whereKey($attributes['survey_response_id'])->valueOrFail('company_id'),
            'survey_question_id' => fn (array $attributes) => SurveyQuestion::factory()->create()->id,
            'text' => 'Great',
        ];
    }
}

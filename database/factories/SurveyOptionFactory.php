<?php

namespace Database\Factories;

use App\Models\SurveyOption;
use App\Models\SurveyQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SurveyOption>
 */
class SurveyOptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'survey_question_id' => SurveyQuestion::factory(),
            'company_id' => fn (array $attributes) => SurveyQuestion::withoutGlobalScopes()->whereKey($attributes['survey_question_id'])->valueOrFail('company_id'),
            'position' => 1,
            'label' => 'Very',
        ];
    }
}

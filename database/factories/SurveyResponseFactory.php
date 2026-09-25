<?php

namespace Database\Factories;

use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SurveyResponse>
 */
class SurveyResponseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'survey_id' => Survey::factory(),
            'company_id' => fn (array $attributes) => Survey::withoutGlobalScopes()->whereKey($attributes['survey_id'])->valueOrFail('company_id'),
            'user_id' => fn (array $attributes) => User::factory()->create(['company_id' => $attributes['company_id']])->id,
            'submitted_at' => now(),
        ];
    }
}

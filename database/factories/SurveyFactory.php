<?php

namespace Database\Factories;

use App\Enums\Audience;
use App\Models\Community;
use App\Models\Survey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Survey>
 */
class SurveyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'company_id' => fn (array $attributes) => Community::withoutGlobalScopes()->whereKey($attributes['community_id'])->valueOrFail('company_id'),
            'title' => 'Amenity satisfaction survey',
            'is_poll' => false,
            'audience' => Audience::Residents,
            'is_anonymous' => false,
            'published_at' => now(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\ViolationRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ViolationRule>
 */
class ViolationRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'company_id' => fn (array $attributes) => Community::withoutGlobalScopes()->whereKey($attributes['community_id'])->valueOrFail('company_id'),
            'title' => fake()->randomElement(['Items stored on balcony', 'Noise after 11pm', 'Pet off leash in common areas', 'Unauthorized parking']),
            'reference' => 'Rules, s. '.fake()->numberBetween(1, 30),
            'cure_days' => 14,
            'fine_cents' => 10000,
            'max_fines' => 3,
            'is_active' => true,
        ];
    }
}

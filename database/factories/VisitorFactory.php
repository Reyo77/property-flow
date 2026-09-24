<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\Visitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Visitor>
 */
class VisitorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'company_id' => fn (array $attributes) => Community::withoutGlobalScopes()->whereKey($attributes['community_id'])->valueOrFail('company_id'),
            'visitor_name' => fake()->name(),
            'purpose' => fake()->randomElement(['Visiting', 'Delivery', 'Contractor', 'Cleaning service']),
            'checked_in_at' => now(),
        ];
    }

    public function checkedOut(): static
    {
        return $this->state(fn (array $attributes) => ['checked_out_at' => now()]);
    }
}

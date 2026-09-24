<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\EntryAuthorization;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EntryAuthorization>
 */
class EntryAuthorizationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'company_id' => fn (array $attributes) => Community::withoutGlobalScopes()->whereKey($attributes['community_id'])->firstOrFail()->company_id,
            'unit_id' => fn (array $attributes) => Unit::factory()->for(Community::withoutGlobalScopes()->whereKey($attributes['community_id'])->firstOrFail())->create()->id,
            'name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'relationship' => fake()->randomElement(['Cleaner', 'Dog walker', 'Family member', 'Contractor']),
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['active' => false]);
    }
}

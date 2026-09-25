<?php

namespace Database\Factories;

use App\Enums\ArchitecturalRequestStatus;
use App\Models\ArchitecturalRequest;
use App\Models\Community;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArchitecturalRequest>
 */
class ArchitecturalRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'company_id' => fn (array $attributes) => Community::withoutGlobalScopes()->whereKey($attributes['community_id'])->valueOrFail('company_id'),
            'unit_id' => fn (array $attributes) => Unit::factory()->create(['community_id' => $attributes['community_id']])->id,
            'title' => fake()->randomElement(['Replace flooring with hardwood', 'Install balcony privacy screen', 'Kitchen renovation']),
            'description' => fake()->paragraph(),
            'status' => ArchitecturalRequestStatus::Submitted,
        ];
    }
}

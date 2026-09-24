<?php

namespace Database\Factories;

use App\Enums\IncidentSeverity;
use App\Models\Community;
use App\Models\IncidentReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncidentReport>
 */
class IncidentReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'company_id' => fn (array $attributes) => Community::withoutGlobalScopes()->whereKey($attributes['community_id'])->firstOrFail()->company_id,
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'location' => fake()->randomElement(['Lobby', 'Parking garage', 'Pool area', 'Hallway']),
            'severity' => IncidentSeverity::Low,
            'occurred_at' => now(),
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => ['resolved_at' => now(), 'resolution_notes' => fake()->sentence()]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\GuestPass;
use App\Models\Resident;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuestPass>
 */
class GuestPassFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'company_id' => fn (array $attributes) => Community::withoutGlobalScopes()->whereKey($attributes['community_id'])->valueOrFail('company_id'),
            'unit_id' => fn (array $attributes) => Unit::factory()->for(Community::withoutGlobalScopes()->whereKey($attributes['community_id'])->firstOrFail())->create()->id,
            'resident_id' => Resident::factory(),
            'guest_name' => fake()->name(),
            'valid_from' => now()->toDateString(),
            'valid_until' => now()->addDay()->toDateString(),
        ];
    }

    public function used(): static
    {
        return $this->state(fn (array $attributes) => ['used_at' => now()]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'valid_from' => now()->subDays(5)->toDateString(),
            'valid_until' => now()->subDay()->toDateString(),
        ]);
    }
}

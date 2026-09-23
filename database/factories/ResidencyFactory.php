<?php

namespace Database\Factories;

use App\Enums\ResidencyType;
use App\Models\Residency;
use App\Models\Resident;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Residency>
 */
class ResidencyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'company_id' => fn (array $attributes) => Unit::withoutGlobalScopes()->whereKey($attributes['unit_id'])->valueOrFail('company_id'),
            'community_id' => fn (array $attributes) => Unit::withoutGlobalScopes()->whereKey($attributes['unit_id'])->valueOrFail('community_id'),
            'resident_id' => fn (array $attributes) => Resident::factory()->state(['company_id' => $attributes['company_id']]),
            'type' => ResidencyType::Owner,
            'is_primary' => true,
            'moved_in_on' => now()->subYear()->toDateString(),
            'moved_out_on' => null,
        ];
    }

    public function tenant(): static
    {
        return $this->state(fn (array $attributes) => ['type' => ResidencyType::Tenant]);
    }

    public function movedOut(): static
    {
        return $this->state(fn (array $attributes) => ['moved_out_on' => now()->subDay()->toDateString()]);
    }
}

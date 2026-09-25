<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\FiscalYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FiscalYear>
 */
class FiscalYearFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = now()->year;

        return [
            'community_id' => Community::factory(),
            'company_id' => fn (array $attributes) => Community::withoutGlobalScopes()->whereKey($attributes['community_id'])->firstOrFail()->company_id,
            'starts_on' => "{$year}-01-01",
            'ends_on' => "{$year}-12-31",
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => ['closed_at' => now()]);
    }
}

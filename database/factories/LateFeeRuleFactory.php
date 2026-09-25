<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\LateFeeRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LateFeeRule>
 */
class LateFeeRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'company_id' => fn (array $attributes) => Community::withoutGlobalScopes()->whereKey($attributes['community_id'])->valueOrFail('company_id'),
            'grace_days' => 10,
            'flat_cents' => 2500,
            'percent_basis_points' => null,
            'minimum_balance_cents' => 0,
            'is_active' => true,
        ];
    }

    public function percent(int $basisPoints): static
    {
        return $this->state(fn (array $attributes) => ['flat_cents' => null, 'percent_basis_points' => $basisPoints]);
    }
}

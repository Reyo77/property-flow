<?php

namespace Database\Factories;

use App\Enums\RecurringChargeMethod;
use App\Models\ChargeType;
use App\Models\Community;
use App\Models\RecurringCharge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringCharge>
 */
class RecurringChargeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'company_id' => fn (array $attributes) => Community::withoutGlobalScopes()->whereKey($attributes['community_id'])->valueOrFail('company_id'),
            'charge_type_id' => fn (array $attributes) => ChargeType::factory()->create(['community_id' => $attributes['community_id']])->id,
            'description' => 'Monthly fees',
            'method' => RecurringChargeMethod::Fixed,
            'amount_cents' => 45000,
            'starts_on' => now()->startOfMonth()->subYear()->toDateString(),
            'is_active' => true,
        ];
    }

    /**
     * Split a community-wide total between units by unit factor.
     */
    public function byUnitFactor(int $totalCents): static
    {
        return $this->state(fn (array $attributes) => ['method' => RecurringChargeMethod::UnitFactor, 'amount_cents' => $totalCents]);
    }
}

<?php

namespace Database\Factories;

use App\Enums\SystemAccount;
use App\Models\ChargeType;
use App\Models\Community;
use App\Support\Finance\ChartOfAccounts;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChargeType>
 */
class ChargeTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'company_id' => fn (array $attributes) => Community::withoutGlobalScopes()->whereKey($attributes['community_id'])->firstOrFail()->company_id,
            'account_id' => fn (array $attributes) => app(ChartOfAccounts::class)->account(
                Community::withoutGlobalScopes()->whereKey($attributes['community_id'])->firstOrFail(),
                SystemAccount::Assessments,
            )->id,
            'name' => fake()->randomElement(['Monthly fees', 'Parking spot', 'Storage locker', 'Move-in fee']),
            'default_amount_cents' => fake()->numberBetween(5000, 50000),
            'is_active' => true,
        ];
    }
}

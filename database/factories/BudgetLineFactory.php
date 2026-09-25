<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\BudgetLine;
use App\Models\Community;
use App\Models\FiscalYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BudgetLine>
 */
class BudgetLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'company_id' => fn (array $attributes) => Community::withoutGlobalScopes()->whereKey($attributes['community_id'])->valueOrFail('company_id'),
            'fiscal_year_id' => fn (array $attributes) => FiscalYear::factory()->create(['community_id' => $attributes['community_id']])->id,
            'account_id' => fn (array $attributes) => Account::factory()->create(['community_id' => $attributes['community_id']])->id,
            'annual_cents' => fake()->numberBetween(100_000, 5_000_000),
        ];
    }
}

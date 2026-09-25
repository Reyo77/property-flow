<?php

namespace Database\Factories;

use App\Enums\SystemAccount;
use App\Models\BankStatement;
use App\Models\Community;
use App\Support\Finance\ChartOfAccounts;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankStatement>
 */
class BankStatementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'company_id' => fn (array $attributes) => Community::withoutGlobalScopes()->whereKey($attributes['community_id'])->valueOrFail('company_id'),
            'account_id' => fn (array $attributes) => app(ChartOfAccounts::class)->account(
                Community::withoutGlobalScopes()->whereKey($attributes['community_id'])->firstOrFail(),
                SystemAccount::Cash,
            )->id,
            'starts_on' => now()->subMonth()->startOfMonth()->toDateString(),
            'ends_on' => now()->subMonth()->endOfMonth()->toDateString(),
            'closing_balance_cents' => 0,
        ];
    }
}

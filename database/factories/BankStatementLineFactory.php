<?php

namespace Database\Factories;

use App\Models\BankStatement;
use App\Models\BankStatementLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankStatementLine>
 */
class BankStatementLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bank_statement_id' => BankStatement::factory(),
            'company_id' => fn (array $attributes) => BankStatement::withoutGlobalScopes()->whereKey($attributes['bank_statement_id'])->valueOrFail('company_id'),
            'posted_on' => now()->subMonth()->toDateString(),
            'description' => fake()->randomElement(['DEPOSIT', 'CHQ 1041', 'EFT CREDIT', 'SERVICE CHARGE']),
            'amount_cents' => fake()->numberBetween(-100_000, 100_000),
        ];
    }
}

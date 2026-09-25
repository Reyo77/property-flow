<?php

namespace Database\Factories;

use App\Actions\Finance\IssueInvoice;
use App\Models\Invoice;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A bare invoice row with no ledger posting. Anything that asserts on balances must issue
 * through {@see IssueInvoice} instead.
 *
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'community_id' => fn (array $attributes) => Unit::withoutGlobalScopes()->whereKey($attributes['unit_id'])->firstOrFail()->community_id,
            'company_id' => fn (array $attributes) => Unit::withoutGlobalScopes()->whereKey($attributes['unit_id'])->firstOrFail()->company_id,
            'number' => fake()->unique()->numberBetween(1, 999999),
            'issued_on' => now()->toDateString(),
            'due_on' => now()->addDays(14)->toDateString(),
            'total_cents' => 10000,
        ];
    }
}

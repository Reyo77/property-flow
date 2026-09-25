<?php

namespace Database\Factories;

use App\Actions\Finance\RecordPayment;
use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A bare payment row with no ledger posting. Anything that asserts on balances must record
 * through {@see RecordPayment} instead.
 *
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
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
            'method' => PaymentMethod::Cheque,
            'amount_cents' => 10000,
            'received_on' => now()->toDateString(),
        ];
    }
}

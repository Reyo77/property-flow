<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentAllocation>
 */
class PaymentAllocationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'company_id' => fn (array $attributes) => Payment::withoutGlobalScopes()->whereKey($attributes['payment_id'])->firstOrFail()->company_id,
            'invoice_id' => fn (array $attributes) => Invoice::factory()->create([
                'unit_id' => Payment::withoutGlobalScopes()->whereKey($attributes['payment_id'])->firstOrFail()->unit_id,
            ])->id,
            'amount_cents' => 5000,
        ];
    }
}

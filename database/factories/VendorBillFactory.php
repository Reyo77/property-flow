<?php

namespace Database\Factories;

use App\Enums\VendorBillStatus;
use App\Models\Account;
use App\Models\Community;
use App\Models\Vendor;
use App\Models\VendorBill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A bill record only — nothing posted to the ledger. Use the finance actions for that.
 *
 * @extends Factory<VendorBill>
 */
class VendorBillFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'company_id' => fn (array $attributes) => Community::withoutGlobalScopes()->whereKey($attributes['community_id'])->valueOrFail('company_id'),
            'vendor_id' => fn (array $attributes) => Vendor::factory()->create(['company_id' => $attributes['company_id']])->id,
            'account_id' => fn (array $attributes) => Account::factory()->create(['community_id' => $attributes['community_id']])->id,
            'number' => fn () => fake()->unique()->numberBetween(1, 1_000_000),
            'description' => fake()->sentence(4),
            'amount_cents' => fake()->numberBetween(10000, 300000),
            'billed_on' => now()->toDateString(),
            'due_on' => now()->addDays(30)->toDateString(),
            'status' => VendorBillStatus::Pending,
        ];
    }
}

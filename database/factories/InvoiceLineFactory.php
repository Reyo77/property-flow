<?php

namespace Database\Factories;

use App\Enums\SystemAccount;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Support\Finance\ChartOfAccounts;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceLine>
 */
class InvoiceLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'company_id' => fn (array $attributes) => Invoice::withoutGlobalScopes()->whereKey($attributes['invoice_id'])->firstOrFail()->company_id,
            'account_id' => fn (array $attributes) => app(ChartOfAccounts::class)->account(
                Invoice::withoutGlobalScopes()->whereKey($attributes['invoice_id'])->firstOrFail()->community()->withoutGlobalScopes()->firstOrFail(),
                SystemAccount::Assessments,
            )->id,
            'description' => 'Monthly fees',
            'amount_cents' => 10000,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Actions\Finance\PostJournalEntry;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\LedgerEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Creates a single unbalanced line. Only for tests that need a row to exist; anything that
 * asserts on balances must post through {@see PostJournalEntry}.
 *
 * @extends Factory<LedgerEntry>
 */
class LedgerEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'journal_entry_id' => JournalEntry::factory(),
            'community_id' => fn (array $attributes) => JournalEntry::withoutGlobalScopes()->whereKey($attributes['journal_entry_id'])->firstOrFail()->community_id,
            'company_id' => fn (array $attributes) => JournalEntry::withoutGlobalScopes()->whereKey($attributes['journal_entry_id'])->firstOrFail()->company_id,
            'account_id' => fn (array $attributes) => Account::factory()->create(['community_id' => $attributes['community_id']])->id,
            'posted_on' => now()->toDateString(),
            'debit_cents' => 1000,
            'credit_cents' => 0,
        ];
    }
}

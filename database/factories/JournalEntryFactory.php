<?php

namespace Database\Factories;

use App\Actions\Finance\PostJournalEntry;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Creates a bare entry header with no lines. Real postings go through
 * {@see PostJournalEntry}, which guarantees balance; this factory exists
 * for tests that only need a header row (e.g. tenant isolation).
 *
 * @extends Factory<JournalEntry>
 */
class JournalEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fiscal_year_id' => FiscalYear::factory(),
            'community_id' => fn (array $attributes) => FiscalYear::withoutGlobalScopes()->whereKey($attributes['fiscal_year_id'])->firstOrFail()->community_id,
            'company_id' => fn (array $attributes) => FiscalYear::withoutGlobalScopes()->whereKey($attributes['fiscal_year_id'])->firstOrFail()->company_id,
            'posted_on' => now()->toDateString(),
            'memo' => fake()->sentence(3),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\ContentReport;
use App\Models\ForumTopic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentReport>
 */
class ContentReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reportable_type' => (new ForumTopic)->getMorphClass(),
            'reportable_id' => ForumTopic::factory(),
            'community_id' => fn (array $attributes) => ForumTopic::withoutGlobalScopes()->whereKey($attributes['reportable_id'])->valueOrFail('community_id'),
            'company_id' => fn (array $attributes) => ForumTopic::withoutGlobalScopes()->whereKey($attributes['reportable_id'])->valueOrFail('company_id'),
            'reported_by_id' => fn (array $attributes) => User::factory()->create(['company_id' => $attributes['company_id']])->id,
            'reason' => 'Spam',
        ];
    }
}

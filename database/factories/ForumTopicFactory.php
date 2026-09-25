<?php

namespace Database\Factories;

use App\Enums\ForumTopicKind;
use App\Models\Community;
use App\Models\ForumTopic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForumTopic>
 */
class ForumTopicFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'company_id' => fn (array $attributes) => Community::withoutGlobalScopes()->whereKey($attributes['community_id'])->valueOrFail('company_id'),
            'author_id' => fn (array $attributes) => User::factory()->create(['company_id' => $attributes['company_id']])->id,
            'kind' => ForumTopicKind::Discussion,
            'title' => fake()->sentence(5),
            'body' => fake()->paragraph(),
            'last_activity_at' => now(),
        ];
    }
}

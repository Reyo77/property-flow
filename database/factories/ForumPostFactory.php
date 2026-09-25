<?php

namespace Database\Factories;

use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForumPost>
 */
class ForumPostFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'forum_topic_id' => ForumTopic::factory(),
            'company_id' => fn (array $attributes) => ForumTopic::withoutGlobalScopes()->whereKey($attributes['forum_topic_id'])->valueOrFail('company_id'),
            'author_id' => fn (array $attributes) => User::factory()->create(['company_id' => $attributes['company_id']])->id,
            'body' => fake()->sentence(),
        ];
    }
}

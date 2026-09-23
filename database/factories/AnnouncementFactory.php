<?php

namespace Database\Factories;

use App\Enums\AnnouncementAudience;
use App\Models\Announcement;
use App\Models\Community;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'company_id' => fn (array $attributes) => Community::withoutGlobalScopes()->whereKey($attributes['community_id'])->valueOrFail('company_id'),
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'audience_type' => AnnouncementAudience::Community,
            'pinned' => false,
        ];
    }

    /**
     * Publish it now, as the create flow does when there is no schedule.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => ['published_at' => now()]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => ['publish_at' => now()->addDay()]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['publish_at' => null, 'published_at' => null]);
    }

    public function pinned(): static
    {
        return $this->state(fn (array $attributes) => ['pinned' => true]);
    }
}

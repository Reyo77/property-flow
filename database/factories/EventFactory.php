<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('+1 day', '+2 months');

        return [
            'community_id' => Community::factory(),
            'company_id' => fn (array $attributes) => Community::withoutGlobalScopes()->whereKey($attributes['community_id'])->valueOrFail('company_id'),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'location' => fake()->randomElement(['Party room', 'Rooftop terrace', 'Lobby', 'Community garden']),
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+2 hours'),
        ];
    }

    public function past(): static
    {
        return $this->state(function (array $attributes) {
            $startsAt = fake()->dateTimeBetween('-2 months', '-1 day');

            return [
                'starts_at' => $startsAt,
                'ends_at' => (clone $startsAt)->modify('+2 hours'),
            ];
        });
    }
}

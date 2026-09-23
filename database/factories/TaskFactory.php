<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\Community;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'company_id' => fn (array $attributes) => Community::withoutGlobalScopes()->whereKey($attributes['community_id'])->valueOrFail('company_id'),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'due_on' => fake()->optional()->dateTimeBetween('now', '+2 weeks'),
            'status' => TaskStatus::Open,
        ];
    }

    public function done(): static
    {
        return $this->state(fn (array $attributes) => ['status' => TaskStatus::Done]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => ['due_on' => now()->subDay()->toDateString()]);
    }
}

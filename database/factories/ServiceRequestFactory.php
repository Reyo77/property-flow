<?php

namespace Database\Factories;

use App\Enums\ServiceRequestCategory;
use App\Enums\ServiceRequestPriority;
use App\Enums\ServiceRequestStatus;
use App\Models\Community;
use App\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceRequest>
 */
class ServiceRequestFactory extends Factory
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
            'description' => fake()->paragraph(),
            'category' => ServiceRequestCategory::Plumbing,
            'priority' => ServiceRequestPriority::Medium,
            'status' => ServiceRequestStatus::Open,
            'entry_permission' => false,
        ];
    }

    public function assigned(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ServiceRequestStatus::Assigned]);
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ServiceRequestStatus::Resolved]);
    }

    public function urgent(): static
    {
        return $this->state(fn (array $attributes) => ['priority' => ServiceRequestPriority::Urgent]);
    }
}

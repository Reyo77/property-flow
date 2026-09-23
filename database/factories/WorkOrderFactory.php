<?php

namespace Database\Factories;

use App\Enums\Assignee;
use App\Enums\WorkOrderStatus;
use App\Models\Community;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrder>
 */
class WorkOrderFactory extends Factory
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
            'description' => fake()->optional()->paragraph(),
            'status' => WorkOrderStatus::Pending,
        ];
    }

    public function assignedToUser(int $userId): static
    {
        return $this->state(fn (array $attributes) => [
            'assignee_type' => Assignee::Staff,
            'assigned_user_id' => $userId,
            'assigned_vendor_id' => null,
        ]);
    }

    public function assignedToVendor(int $vendorId): static
    {
        return $this->state(fn (array $attributes) => [
            'assignee_type' => Assignee::Vendor,
            'assigned_vendor_id' => $vendorId,
            'assigned_user_id' => null,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => ['status' => WorkOrderStatus::InProgress]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => WorkOrderStatus::Completed, 'completed_at' => now()]);
    }
}

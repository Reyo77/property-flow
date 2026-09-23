<?php

namespace Database\Factories;

use App\Models\ServiceRequest;
use App\Models\ServiceRequestComment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceRequestComment>
 */
class ServiceRequestCommentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_request_id' => ServiceRequest::factory(),
            'company_id' => fn (array $attributes) => ServiceRequest::withoutGlobalScopes()->whereKey($attributes['service_request_id'])->valueOrFail('company_id'),
            'body' => fake()->sentence(),
            'visible_to_resident' => true,
        ];
    }

    public function internal(): static
    {
        return $this->state(fn (array $attributes) => ['visible_to_resident' => false]);
    }
}

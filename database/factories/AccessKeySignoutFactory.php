<?php

namespace Database\Factories;

use App\Models\AccessKey;
use App\Models\AccessKeySignout;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccessKeySignout>
 */
class AccessKeySignoutFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'access_key_id' => AccessKey::factory(),
            'company_id' => fn (array $attributes) => AccessKey::withoutGlobalScopes()->whereKey($attributes['access_key_id'])->firstOrFail()->company_id,
            'signed_out_to' => fake()->name(),
            'signed_out_to_phone' => fake()->phoneNumber(),
            'signed_out_at' => now(),
        ];
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'signed_out_at' => now()->subDays(3),
            'due_back_at' => now()->subDay(),
        ]);
    }

    public function returned(): static
    {
        return $this->state(fn (array $attributes) => ['returned_at' => now()]);
    }
}

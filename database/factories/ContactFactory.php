<?php

namespace Database\Factories;

use App\Enums\ContactCategory;
use App\Models\Community;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'company_id' => fn (array $attributes) => Community::withoutGlobalScopes()->whereKey($attributes['community_id'])->valueOrFail('company_id'),
            'name' => fake()->name(),
            'title' => fake()->jobTitle(),
            'category' => ContactCategory::Staff,
            'phone' => fake()->numerify('416-555-####'),
            'email' => fake()->unique()->safeEmail(),
            'visible_to_residents' => true,
        ];
    }

    public function emergency(): static
    {
        return $this->state(fn (array $attributes) => ['category' => ContactCategory::Emergency]);
    }

    public function staffOnly(): static
    {
        return $this->state(fn (array $attributes) => ['visible_to_residents' => false]);
    }
}

<?php

namespace Database\Factories;

use App\Enums\WebhookEvent;
use App\Models\Company;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookEndpoint>
 */
class WebhookEndpointFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'url' => 'https://hooks.example.test/'.fake()->uuid(),
            'description' => fake()->words(3, true),
            'secret' => WebhookEndpoint::generateSecret(),
            'events' => array_map(fn (WebhookEvent $event) => $event->value, WebhookEvent::subscribable()),
            'is_active' => true,
        ];
    }

    /**
     * @param  list<WebhookEvent>  $events
     */
    public function listeningTo(array $events): static
    {
        return $this->state(fn (array $attributes) => ['events' => array_map(fn (WebhookEvent $event) => $event->value, $events)]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false, 'disabled_at' => now()]);
    }
}

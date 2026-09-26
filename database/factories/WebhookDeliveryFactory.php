<?php

namespace Database\Factories;

use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEvent;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookDelivery>
 */
class WebhookDeliveryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'webhook_endpoint_id' => WebhookEndpoint::factory(),
            'company_id' => fn (array $attributes) => WebhookEndpoint::withoutGlobalScopes()->whereKey($attributes['webhook_endpoint_id'])->valueOrFail('company_id'),
            'uuid' => (string) Str::uuid(),
            'event' => WebhookEvent::Ping->value,
            'payload' => ['event' => WebhookEvent::Ping->value, 'data' => []],
            'status' => WebhookDeliveryStatus::Pending,
        ];
    }
}

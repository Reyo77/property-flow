<?php

namespace App\Support\Webhooks;

use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEvent;
use App\Jobs\SendWebhookDelivery;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Hands an event to every active endpoint of the company that subscribed to it. Each gets its
 * own delivery record, sent by a queued job once the surrounding transaction has committed, so
 * nothing is announced that then rolls back and a slow receiver never holds up the request.
 *
 * Payload: {"id": delivery uuid, "event": "package.logged", "created_at": ISO 8601,
 *           "company_id": 1, "community_id": 1, "data": {…the API resource…}}
 */
class Webhooks
{
    public function __construct(private readonly WebhookPayloads $payloads) {}

    /**
     * Announces that something happened to a record (a service request, a payment…). The payload
     * is only built when some endpoint is listening.
     */
    public function dispatch(WebhookEvent $event, Model $subject): void
    {
        $companyId = (int) $subject->getAttribute('company_id');

        $endpoints = WebhookEndpoint::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->get()
            ->filter(fn (WebhookEndpoint $endpoint) => $endpoint->listensTo($event));

        if ($endpoints->isEmpty()) {
            return;
        }

        $communityId = $subject->getAttribute('community_id');
        $data = $this->payloads->for($event, $subject);

        foreach ($endpoints as $endpoint) {
            $this->send($endpoint, $event, is_int($communityId) ? $communityId : null, $data);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function send(WebhookEndpoint $endpoint, WebhookEvent $event, ?int $communityId, array $data): WebhookDelivery
    {
        $uuid = (string) Str::uuid();

        $delivery = new WebhookDelivery;
        $delivery->forceFill([
            'company_id' => $endpoint->company_id,
            'webhook_endpoint_id' => $endpoint->id,
            'uuid' => $uuid,
            'event' => $event->value,
            'payload' => [
                'id' => $uuid,
                'event' => $event->value,
                'created_at' => now()->toIso8601String(),
                'company_id' => $endpoint->company_id,
                'community_id' => $communityId,
                'data' => $data,
            ],
            'status' => WebhookDeliveryStatus::Pending,
        ])->save();

        SendWebhookDelivery::dispatch($delivery->id)->afterCommit();

        return $delivery;
    }
}

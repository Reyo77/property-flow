<?php

namespace App\Jobs;

use App\Enums\WebhookDeliveryStatus;
use App\Models\WebhookDelivery;
use App\Support\Webhooks\WebhookSignature;
use App\Support\Webhooks\WebhookUrlGuard;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Makes one attempt at a delivery. A failure schedules the next attempt after the configured
 * delay; when the delays run out the delivery is marked failed, and an endpoint that keeps failing
 * is switched off. Retries are counted on the delivery itself, so a redelivery starts fresh.
 */
class SendWebhookDelivery implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $deliveryId) {}

    public function handle(WebhookUrlGuard $guard): void
    {
        $delivery = WebhookDelivery::query()->withoutGlobalScopes()->with(['endpoint' => fn ($query) => $query->withoutGlobalScopes()])->find($this->deliveryId);

        if ($delivery === null || $delivery->status !== WebhookDeliveryStatus::Pending || ! $delivery->endpoint->is_active) {
            return;
        }

        $delivery->forceFill(['attempts' => $delivery->attempts + 1, 'last_attempted_at' => now()])->save();

        $problem = $guard->problem($delivery->endpoint->url);

        if ($problem !== null) {
            $this->recordFailure($delivery, null, __('Not sent: :problem', ['problem' => $problem]), retry: false);

            return;
        }

        $body = (string) json_encode($delivery->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        try {
            $response = Http::timeout((int) config('webhooks.timeout_seconds'))
                ->withOptions(['allow_redirects' => false])
                ->withHeaders([
                    'User-Agent' => 'PropertyFlow-Webhooks/1.0',
                    'PropertyFlow-Event' => $delivery->event,
                    'PropertyFlow-Delivery' => $delivery->uuid,
                    WebhookSignature::HEADER => WebhookSignature::header($body, $delivery->endpoint->secret, now()->getTimestamp()),
                ])
                ->withBody($body, 'application/json')
                ->post($delivery->endpoint->url);
        } catch (ConnectionException $exception) {
            $this->recordFailure($delivery, null, Str::limit($exception->getMessage(), 500));

            return;
        }

        if ($response->successful()) {
            $delivery->forceFill([
                'status' => WebhookDeliveryStatus::Succeeded,
                'response_status' => $response->status(),
                'response_excerpt' => Str::limit($response->body(), 500),
                'delivered_at' => now(),
            ])->save();
            $delivery->endpoint->forceFill(['consecutive_failures' => 0, 'last_delivered_at' => now()])->save();

            return;
        }

        $this->recordFailure($delivery, $response->status(), Str::limit($response->body(), 500));
    }

    private function recordFailure(WebhookDelivery $delivery, ?int $status, string $excerpt, bool $retry = true): void
    {
        $delays = array_values((array) config('webhooks.retry_after_seconds'));
        $nextDelay = $delays[$delivery->attempts - 1] ?? null;

        if ($retry && $nextDelay !== null) {
            $delivery->forceFill(['response_status' => $status, 'response_excerpt' => $excerpt])->save();
            self::dispatch($delivery->id)->delay(now()->addSeconds((int) $nextDelay));

            return;
        }

        $delivery->forceFill(['status' => WebhookDeliveryStatus::Failed, 'response_status' => $status, 'response_excerpt' => $excerpt])->save();

        $endpoint = $delivery->endpoint;
        $failures = $endpoint->consecutive_failures + 1;
        $disable = $failures >= (int) config('webhooks.disable_after_failures');

        $endpoint->forceFill([
            'consecutive_failures' => $failures,
            'is_active' => $disable ? false : $endpoint->is_active,
            'disabled_at' => $disable ? now() : $endpoint->disabled_at,
        ])->save();
    }
}

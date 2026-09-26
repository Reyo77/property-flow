<?php

namespace App\Actions\Webhooks;

use App\Enums\WebhookEvent;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Support\Webhooks\WebhookUrlGuard;
use Illuminate\Validation\ValidationException;

class SaveWebhookEndpoint
{
    public function __construct(private readonly WebhookUrlGuard $guard) {}

    /**
     * Creates or updates an endpoint. A new endpoint gets a fresh signing secret; turning an
     * endpoint back on clears its failure count.
     *
     * @param  list<string>  $events
     *
     * @throws ValidationException
     */
    public function handle(User $user, ?WebhookEndpoint $endpoint, string $url, ?string $description, array $events, bool $active = true): WebhookEndpoint
    {
        $problem = $this->guard->problem($url);

        if ($problem !== null) {
            throw ValidationException::withMessages(['url' => $problem]);
        }

        $allowed = array_map(fn (WebhookEvent $event) => $event->value, WebhookEvent::subscribable());
        $events = array_values(array_unique(array_intersect($events, $allowed)));

        if ($events === []) {
            throw ValidationException::withMessages(['events' => __('Choose at least one event to send.')]);
        }

        $endpoint ??= (new WebhookEndpoint)->forceFill([
            'company_id' => $user->company_id,
            'secret' => WebhookEndpoint::generateSecret(),
            'created_by_id' => $user->id,
        ]);

        $reactivated = $active && ! $endpoint->is_active;

        $endpoint->fill(['url' => $url, 'description' => $description, 'events' => $events]);
        $endpoint->forceFill([
            'is_active' => $active,
            'disabled_at' => $active ? null : ($endpoint->disabled_at ?? now()),
            'consecutive_failures' => $reactivated ? 0 : $endpoint->consecutive_failures,
        ])->save();

        return $endpoint;
    }
}

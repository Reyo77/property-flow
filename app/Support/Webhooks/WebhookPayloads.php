<?php

namespace App\Support\Webhooks;

use App\Enums\WebhookEvent;
use Illuminate\Database\Eloquent\Model;

/**
 * Turns the record an event is about into the `data` of its webhook payload. The implementation
 * lives with the API (App\Http\Webhooks) so a webhook describes a record exactly as the API does.
 */
interface WebhookPayloads
{
    /**
     * @return array<string, mixed>
     */
    public function for(WebhookEvent $event, Model $subject): array;
}

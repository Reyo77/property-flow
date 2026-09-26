<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Models\WebhookEndpoint;

/**
 * Webhooks are company-wide and send data out of the app, so only people who may manage them
 * (company admins by default) can see or change them.
 */
class WebhookEndpointPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCompanyPermission(Permission::ManageWebhooks);
    }

    public function create(User $user): bool
    {
        return $user->hasCompanyPermission(Permission::ManageWebhooks);
    }

    public function update(User $user, WebhookEndpoint $endpoint): bool
    {
        return $user->company_id === $endpoint->company_id && $user->hasCompanyPermission(Permission::ManageWebhooks);
    }

    public function delete(User $user, WebhookEndpoint $endpoint): bool
    {
        return $this->update($user, $endpoint);
    }
}

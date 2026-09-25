<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Community;
use App\Models\RecurringCharge;
use App\Models\User;
use App\Policies\Concerns\ChecksCommunityAccess;

/**
 * Also covers the community's late fee rule and running billing, which sit on the same page.
 */
class RecurringChargePolicy
{
    use ChecksCommunityAccess;

    public function viewAny(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ViewFinance);
    }

    public function create(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ManageFinance);
    }

    public function update(User $user, RecurringCharge $recurringCharge): bool
    {
        return $this->allowedFor($user, $recurringCharge, Permission::ManageFinance);
    }
}

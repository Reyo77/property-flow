<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ChargeType;
use App\Models\Community;
use App\Models\User;
use App\Policies\Concerns\ChecksCommunityAccess;

class ChargeTypePolicy
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

    public function update(User $user, ChargeType $chargeType): bool
    {
        return $this->allowedFor($user, $chargeType, Permission::ManageFinance);
    }
}

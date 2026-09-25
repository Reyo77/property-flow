<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Community;
use App\Models\User;
use App\Policies\Concerns\ChecksCommunityAccess;

class BudgetLinePolicy
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
}

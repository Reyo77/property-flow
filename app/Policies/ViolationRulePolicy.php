<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Community;
use App\Models\User;
use App\Models\ViolationRule;
use App\Policies\Concerns\ChecksCommunityAccess;

class ViolationRulePolicy
{
    use ChecksCommunityAccess;

    public function viewAny(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ViewViolations);
    }

    public function create(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ManageViolations);
    }

    public function update(User $user, ViolationRule $rule): bool
    {
        return $this->allowedFor($user, $rule, Permission::ManageViolations);
    }
}

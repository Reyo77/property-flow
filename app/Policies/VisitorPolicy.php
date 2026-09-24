<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Community;
use App\Models\User;
use App\Models\Visitor;
use App\Policies\Concerns\ChecksCommunityAccess;

/**
 * The visitor log is a front-desk record; residents don't get individual visibility into who
 * else's guests were logged, only staff with visitors.view do.
 */
class VisitorPolicy
{
    use ChecksCommunityAccess;

    public function viewAny(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ViewVisitors);
    }

    public function view(User $user, Visitor $visitor): bool
    {
        return $this->allowedFor($user, $visitor, Permission::ViewVisitors);
    }

    public function create(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ManageVisitors);
    }

    public function update(User $user, Visitor $visitor): bool
    {
        return $this->allowedFor($user, $visitor, Permission::ManageVisitors);
    }
}

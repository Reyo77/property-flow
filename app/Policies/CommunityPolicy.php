<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Community;
use App\Models\User;

class CommunityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCompanyPermission(Permission::ViewCommunities);
    }

    public function view(User $user, Community $community): bool
    {
        return $user->company_id === $community->company_id
            && $user->hasCompanyPermission(Permission::ViewCommunities);
    }

    public function create(User $user): bool
    {
        return $user->company_id !== null
            && $user->hasCompanyPermission(Permission::ManageCommunities);
    }

    public function update(User $user, Community $community): bool
    {
        return $user->company_id === $community->company_id
            && $user->hasCompanyPermission(Permission::ManageCommunities);
    }

    public function delete(User $user, Community $community): bool
    {
        return $this->update($user, $community);
    }
}

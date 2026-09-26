<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Community;
use App\Models\User;
use App\Policies\Concerns\ChecksCommunityAccess;
use App\Policies\Concerns\ChecksResidentAccess;

class CommunityPolicy
{
    use ChecksCommunityAccess, ChecksResidentAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasCompanyPermission(Permission::ViewCommunities);
    }

    public function view(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ViewCommunities);
    }

    /**
     * The community's basic details (name, time zone, currency), which its residents need too.
     */
    public function viewDetails(User $user, Community $community): bool
    {
        return $this->view($user, $community) || $this->isCurrentResidentOf($user, $community->id);
    }

    public function create(User $user): bool
    {
        return $user->hasCompanyPermission(Permission::ManageCommunities);
    }

    public function update(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ManageCommunities);
    }

    public function delete(User $user, Community $community): bool
    {
        return $this->update($user, $community);
    }
}

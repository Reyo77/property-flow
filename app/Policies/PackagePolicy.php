<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Community;
use App\Models\Package;
use App\Models\User;
use App\Policies\Concerns\ChecksCommunityAccess;
use App\Policies\Concerns\ChecksResidentAccess;

class PackagePolicy
{
    use ChecksCommunityAccess, ChecksResidentAccess;

    public function viewAny(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ViewPackages)
            || $this->isCurrentResidentOf($user, $community->id);
    }

    /**
     * Staff with access see any package; a resident sees only their own.
     */
    public function view(User $user, Package $package): bool
    {
        return $this->allowedFor($user, $package, Permission::ViewPackages)
            || ($user->resident !== null && $package->resident_id === $user->resident->id);
    }

    public function create(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ManagePackages);
    }

    public function release(User $user, Package $package): bool
    {
        return $this->allowedFor($user, $package, Permission::ManagePackages);
    }
}

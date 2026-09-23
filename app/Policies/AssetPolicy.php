<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Asset;
use App\Models\Community;
use App\Models\User;
use App\Policies\Concerns\ChecksCommunityAccess;

class AssetPolicy
{
    use ChecksCommunityAccess;

    public function viewAny(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ViewAssets);
    }

    public function view(User $user, Asset $asset): bool
    {
        return $this->allowedFor($user, $asset, Permission::ViewAssets);
    }

    public function create(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ManageAssets);
    }

    public function update(User $user, Asset $asset): bool
    {
        return $this->allowedFor($user, $asset, Permission::ManageAssets);
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $this->update($user, $asset);
    }
}

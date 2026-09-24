<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\AccessKey;
use App\Models\Community;
use App\Models\User;
use App\Policies\Concerns\ChecksCommunityAccess;

class AccessKeyPolicy
{
    use ChecksCommunityAccess;

    public function viewAny(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ViewKeys);
    }

    public function view(User $user, AccessKey $accessKey): bool
    {
        return $this->allowedFor($user, $accessKey, Permission::ViewKeys);
    }

    public function create(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ManageKeys);
    }

    /**
     * Signing a key out or back in.
     */
    public function update(User $user, AccessKey $accessKey): bool
    {
        return $this->allowedFor($user, $accessKey, Permission::ManageKeys);
    }
}

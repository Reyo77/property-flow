<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Building;
use App\Models\Community;
use App\Models\User;
use App\Policies\Concerns\ChecksCommunityAccess;

class BuildingPolicy
{
    use ChecksCommunityAccess;

    public function viewAny(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ViewBuildings);
    }

    public function create(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ManageBuildings);
    }

    public function update(User $user, Building $building): bool
    {
        return $this->allowedFor($user, $building, Permission::ManageBuildings);
    }

    public function delete(User $user, Building $building): bool
    {
        return $this->update($user, $building);
    }
}

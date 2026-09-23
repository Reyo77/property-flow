<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Building;
use App\Models\Community;
use App\Models\User;

class BuildingPolicy
{
    public function viewAny(User $user, Community $community): bool
    {
        return $user->company_id === $community->company_id
            && $user->hasCompanyPermission(Permission::ViewBuildings);
    }

    public function create(User $user, Community $community): bool
    {
        return $user->company_id === $community->company_id
            && $user->hasCompanyPermission(Permission::ManageBuildings);
    }

    public function update(User $user, Building $building): bool
    {
        return $user->company_id === $building->company_id
            && $user->hasCompanyPermission(Permission::ManageBuildings);
    }

    public function delete(User $user, Building $building): bool
    {
        return $this->update($user, $building);
    }
}

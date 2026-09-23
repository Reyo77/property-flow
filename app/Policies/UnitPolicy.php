<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Community;
use App\Models\Unit;
use App\Models\User;

class UnitPolicy
{
    public function viewAny(User $user, Community $community): bool
    {
        return $user->company_id === $community->company_id
            && $user->hasCompanyPermission(Permission::ViewUnits);
    }

    public function create(User $user, Community $community): bool
    {
        return $user->company_id === $community->company_id
            && $user->hasCompanyPermission(Permission::ManageUnits);
    }

    public function update(User $user, Unit $unit): bool
    {
        return $user->company_id === $unit->company_id
            && $user->hasCompanyPermission(Permission::ManageUnits);
    }

    public function delete(User $user, Unit $unit): bool
    {
        return $this->update($user, $unit);
    }

    public function import(User $user, Community $community): bool
    {
        return $user->company_id === $community->company_id
            && $user->hasCompanyPermission(Permission::ImportUnits);
    }
}

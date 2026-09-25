<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Community;
use App\Models\Unit;
use App\Models\User;
use App\Policies\Concerns\ChecksCommunityAccess;
use App\Policies\Concerns\ChecksResidentAccess;

class UnitPolicy
{
    use ChecksCommunityAccess, ChecksResidentAccess;

    public function viewAny(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ViewUnits);
    }

    public function view(User $user, Unit $unit): bool
    {
        return $this->allowedFor($user, $unit, Permission::ViewUnits);
    }

    public function create(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ManageUnits);
    }

    public function update(User $user, Unit $unit): bool
    {
        return $this->allowedFor($user, $unit, Permission::ManageUnits);
    }

    public function delete(User $user, Unit $unit): bool
    {
        return $this->update($user, $unit);
    }

    public function import(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ImportUnits);
    }

    /**
     * Whether the user may add, change and move out the unit's residents.
     */
    public function manageResidents(User $user, Unit $unit): bool
    {
        return $this->allowedFor($user, $unit, Permission::ManageResidents);
    }

    /**
     * Finance staff see any unit's account; its residents see their own.
     */
    public function viewLedger(User $user, Unit $unit): bool
    {
        return $this->allowedFor($user, $unit, Permission::ViewFinance)
            || $this->isCurrentResidentOfUnit($user, $unit->id);
    }
}

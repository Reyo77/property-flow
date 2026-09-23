<?php

namespace App\Policies;

use App\Enums\CompanyRole;
use App\Enums\Permission;
use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCompanyPermission(Permission::ManageRoles);
    }

    public function create(User $user): bool
    {
        return $user->hasCompanyPermission(Permission::ManageRoles);
    }

    public function update(User $user, Role $role): bool
    {
        return $this->ownsRole($user, $role)
            && (CompanyRole::tryFrom($role->name)?->isEditable() ?? true)
            && $user->hasCompanyPermission(Permission::ManageRoles);
    }

    /**
     * Only custom roles can be deleted, and only once nobody has them.
     */
    public function delete(User $user, Role $role): bool
    {
        return $this->ownsRole($user, $role)
            && CompanyRole::tryFrom($role->name) === null
            && $user->hasCompanyPermission(Permission::ManageRoles)
            && ! $role->users()->exists();
    }

    private function ownsRole(User $user, Role $role): bool
    {
        return $user->company_id !== null && $role->getAttribute('company_id') === $user->company_id;
    }
}

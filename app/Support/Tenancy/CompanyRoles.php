<?php

namespace App\Support\Tenancy;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

/**
 * Looks up a company's roles, which spatie stores per company (team).
 */
class CompanyRoles
{
    /**
     * @return Builder<Role>
     */
    public static function query(int $companyId): Builder
    {
        return Role::query()->where('company_id', $companyId);
    }

    public static function find(int $companyId, string $name): ?Role
    {
        return self::query($companyId)->where('name', $name)->first();
    }

    /**
     * Whether the actor holds every permission in the list, so granting them is not an escalation.
     *
     * @param  iterable<string>  $permissionNames
     */
    public static function actorHoldsAll(User $actor, iterable $permissionNames): bool
    {
        foreach ($permissionNames as $permissionName) {
            $permission = Permission::tryFrom($permissionName);

            if ($permission === null || ! $actor->hasCompanyPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    public static function actorCanGrant(User $actor, Role $role): bool
    {
        return self::actorHoldsAll($actor, $role->permissions->pluck('name')->all());
    }
}

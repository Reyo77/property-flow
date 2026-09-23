<?php

namespace App\Actions\Team;

use App\Enums\CompanyRole;
use App\Enums\Permission;
use App\Models\User;
use App\Support\Tenancy\CompanyRoles;
use App\Support\Tenancy\PermissionTeam;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;

class SaveRole
{
    /**
     * Create a custom role, or change a role's name (custom roles only) and permissions.
     *
     * @param  list<string>  $permissionNames
     *
     * @throws ValidationException
     */
    public function handle(User $actor, ?Role $role, string $name, array $permissionNames): Role
    {
        $companyId = $actor->company_id ?? throw new InvalidArgumentException('Only company members can manage roles.');
        $isCustom = $role === null || CompanyRole::tryFrom($role->name) === null;

        Validator::make(['name' => $name, 'permissions' => $permissionNames], [
            'name' => $isCustom ? [
                'required', 'string', 'max:100',
                Rule::unique('roles', 'name')->where('company_id', $companyId)->ignore($role?->id),
                Rule::notIn(array_map(fn ($case) => $case->value, CompanyRole::cases())),
            ] : [],
            'permissions' => ['array'],
            'permissions.*' => [Rule::enum(Permission::class)],
        ])->validate();

        if (! CompanyRoles::actorHoldsAll($actor, $permissionNames)) {
            throw ValidationException::withMessages(['permissions' => __('You cannot grant permissions you do not have.')]);
        }

        return PermissionTeam::run($companyId, function () use ($companyId, $role, $isCustom, $name, $permissionNames): Role {
            if ($role === null) {
                $role = new Role(['name' => $name]);
                $role->forceFill(['company_id' => $companyId])->save();
            } elseif ($isCustom) {
                $role->update(['name' => $name]);
            }

            $role->syncPermissions($permissionNames);

            return $role;
        });
    }
}

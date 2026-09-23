<?php

namespace App\Actions\Companies;

use App\Enums\CompanyRole;
use App\Enums\Permission as PermissionName;
use App\Models\Company;
use App\Support\Tenancy\PermissionTeam;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SyncDefaultRoles
{
    /**
     * Create the company's default roles and grant each its default permissions.
     */
    public function handle(Company $company): void
    {
        foreach (PermissionName::cases() as $permission) {
            Permission::findOrCreate($permission->value);
        }

        PermissionTeam::run($company->id, function (): void {
            foreach (CompanyRole::cases() as $companyRole) {
                Role::findOrCreate($companyRole->value)->syncPermissions(
                    array_map(fn (PermissionName $permission) => $permission->value, $companyRole->defaultPermissions()),
                );
            }
        });
    }
}

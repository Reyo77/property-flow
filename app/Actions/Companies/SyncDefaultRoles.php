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

    /**
     * Create permissions added since the last deploy and grant each to every company's default
     * roles that include it by default. Permissions that already existed are left alone, so a
     * company that removed one from a role keeps it removed.
     *
     * @return list<string> the permissions that were created
     */
    public function grantNewPermissions(): array
    {
        $existing = Permission::query()->pluck('name')->all();
        $new = array_values(array_filter(PermissionName::cases(), fn (PermissionName $permission) => ! in_array($permission->value, $existing, true)));

        if ($new === []) {
            return [];
        }

        foreach ($new as $permission) {
            Permission::findOrCreate($permission->value);
        }

        Company::query()->each(function (Company $company) use ($new): void {
            PermissionTeam::run($company->id, function () use ($new): void {
                foreach (CompanyRole::cases() as $companyRole) {
                    $granted = array_filter($new, fn (PermissionName $permission) => in_array($permission, $companyRole->defaultPermissions(), true));

                    if ($granted !== []) {
                        Role::findOrCreate($companyRole->value)->givePermissionTo(array_map(fn (PermissionName $permission) => $permission->value, array_values($granted)));
                    }
                }
            });
        });

        return array_map(fn (PermissionName $permission) => $permission->value, $new);
    }
}

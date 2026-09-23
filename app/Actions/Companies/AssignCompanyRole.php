<?php

namespace App\Actions\Companies;

use App\Enums\CompanyRole;
use App\Models\User;
use App\Support\Tenancy\PermissionTeam;
use InvalidArgumentException;

class AssignCompanyRole
{
    public function handle(User $user, CompanyRole $role): void
    {
        if ($user->company_id === null) {
            throw new InvalidArgumentException('Only users that belong to a company can be given a company role.');
        }

        PermissionTeam::run($user->company_id, function () use ($user, $role): void {
            $user->assignRole($role->value);
        });

        $user->unsetRelation('roles')->unsetRelation('permissions');
    }
}

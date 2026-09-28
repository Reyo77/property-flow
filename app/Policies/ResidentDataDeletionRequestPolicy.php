<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ResidentDataDeletionRequest;
use App\Models\User;

class ResidentDataDeletionRequestPolicy
{
    public function create(User $user): bool
    {
        return $user->resident !== null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasCompanyPermission(Permission::ManageResidents);
    }

    public function review(User $user, ResidentDataDeletionRequest $request): bool
    {
        return $user->company_id === $request->company_id && $user->hasCompanyPermission(Permission::ManageResidents);
    }
}

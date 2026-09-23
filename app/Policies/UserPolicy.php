<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

/**
 * Managing the people in a company: team members and resident logins.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCompanyPermission(Permission::ViewTeam);
    }

    public function invite(User $user): bool
    {
        return $user->hasCompanyPermission(Permission::ManageTeam);
    }

    /**
     * Change another member's role and communities. Nobody can change their own access.
     */
    public function update(User $user, User $member): bool
    {
        return $user->company_id !== null
            && $user->company_id === $member->company_id
            && ! $user->is($member)
            && $user->hasCompanyPermission(Permission::ManageTeam);
    }

    public function resetPassword(User $user, User $member): bool
    {
        return $this->update($user, $member);
    }

    public function deactivate(User $user, User $member): bool
    {
        return $this->update($user, $member);
    }
}

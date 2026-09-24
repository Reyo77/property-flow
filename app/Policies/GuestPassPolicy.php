<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Community;
use App\Models\GuestPass;
use App\Models\User;
use App\Policies\Concerns\ChecksCommunityAccess;
use App\Policies\Concerns\ChecksResidentAccess;

class GuestPassPolicy
{
    use ChecksCommunityAccess, ChecksResidentAccess;

    public function viewAny(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ManageVisitors)
            || $this->isCurrentResidentOf($user, $community->id);
    }

    /**
     * Staff who manage visitors see any pass; a resident sees only their own.
     */
    public function view(User $user, GuestPass $guestPass): bool
    {
        return $this->allowedFor($user, $guestPass, Permission::ManageVisitors)
            || ($user->resident !== null && $guestPass->resident_id === $user->resident->id);
    }

    /**
     * A resident creates a pass for themselves; staff may also create one on a resident's behalf.
     */
    public function create(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ManageVisitors)
            || $this->isCurrentResidentOf($user, $community->id);
    }

    public function redeem(User $user, GuestPass $guestPass): bool
    {
        return $this->allowedFor($user, $guestPass, Permission::ManageVisitors);
    }
}

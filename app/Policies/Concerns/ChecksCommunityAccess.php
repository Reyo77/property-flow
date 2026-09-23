<?php

namespace App\Policies\Concerns;

use App\Enums\Permission;
use App\Models\Building;
use App\Models\Community;
use App\Models\Unit;
use App\Models\User;

trait ChecksCommunityAccess
{
    /**
     * Whether the user works in the community and their role grants the permission.
     */
    protected function allowedIn(User $user, Community $community, Permission $permission): bool
    {
        return $user->canAccessCommunity($community) && $user->hasCompanyPermission($permission);
    }

    /**
     * The same check for a record in a community, using its ids so lists do not load each community.
     */
    protected function allowedFor(User $user, Building|Unit $record, Permission $permission): bool
    {
        return $user->canAccessCommunityById($record->company_id, $record->community_id)
            && $user->hasCompanyPermission($permission);
    }
}

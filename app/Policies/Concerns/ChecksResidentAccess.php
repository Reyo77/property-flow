<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait ChecksResidentAccess
{
    /**
     * Whether the user is currently (not formerly) a resident of the community.
     */
    protected function isCurrentResidentOf(User $user, int $communityId): bool
    {
        return $user->resident !== null
            && $user->resident->residencies()->where('community_id', $communityId)->active()->exists();
    }

    /**
     * Whether the user currently lives in (or owns) the unit.
     */
    protected function isCurrentResidentOfUnit(User $user, int $unitId): bool
    {
        return $user->resident !== null
            && $user->resident->residencies()->where('unit_id', $unitId)->active()->exists();
    }
}

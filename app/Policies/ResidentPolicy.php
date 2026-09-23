<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Community;
use App\Models\Resident;
use App\Models\User;
use App\Policies\Concerns\ChecksCommunityAccess;

/**
 * Resident abilities are always checked within a community the resident has lived in.
 */
class ResidentPolicy
{
    use ChecksCommunityAccess;

    public function viewAny(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ViewResidents);
    }

    public function view(User $user, Resident $resident, Community $community): bool
    {
        return $this->belongsTo($resident, $community)
            && $this->allowedIn($user, $community, Permission::ViewResidents);
    }

    public function update(User $user, Resident $resident, Community $community): bool
    {
        return $this->belongsTo($resident, $community)
            && $this->allowedIn($user, $community, Permission::ManageResidents);
    }

    /**
     * Whether the user may invite the resident to the resident portal.
     */
    public function invite(User $user, Resident $resident, Community $community): bool
    {
        return $resident->email !== null
            && ! $resident->hasPortalAccess()
            && $this->update($user, $resident, $community);
    }

    private function belongsTo(Resident $resident, Community $community): bool
    {
        return $resident->company_id === $community->company_id
            && $resident->residencies()->where('community_id', $community->id)->exists();
    }
}

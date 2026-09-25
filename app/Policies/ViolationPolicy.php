<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\ResidencyType;
use App\Models\Community;
use App\Models\Residency;
use App\Models\User;
use App\Models\Violation;
use App\Policies\Concerns\ChecksCommunityAccess;
use App\Policies\Concerns\ChecksResidentAccess;

class ViolationPolicy
{
    use ChecksCommunityAccess, ChecksResidentAccess;

    /**
     * Staff see every violation; owners see the ones against their own units.
     */
    public function viewAny(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ViewViolations)
            || $this->isCurrentResidentOf($user, $community->id);
    }

    /**
     * Notices go to owners; tenants don't see them (the owner answers to the corporation).
     */
    public function view(User $user, Violation $violation): bool
    {
        return $this->allowedFor($user, $violation, Permission::ViewViolations)
            || ($user->resident !== null && Residency::query()
                ->where('unit_id', $violation->unit_id)
                ->where('resident_id', $user->resident->id)
                ->where('type', ResidencyType::Owner)
                ->active()
                ->exists());
    }

    public function create(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ReportViolations);
    }

    public function manage(User $user, Violation $violation): bool
    {
        return $this->allowedFor($user, $violation, Permission::ManageViolations);
    }
}

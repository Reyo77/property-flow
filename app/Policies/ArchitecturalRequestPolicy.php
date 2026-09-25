<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ArchitecturalRequest;
use App\Models\Community;
use App\Models\User;
use App\Policies\Concerns\ChecksCommunityAccess;
use App\Policies\Concerns\ChecksResidentAccess;
use App\Support\Governance\VotingRoll;

class ArchitecturalRequestPolicy
{
    use ChecksCommunityAccess, ChecksResidentAccess;

    public function viewAny(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ViewGovernance)
            || $this->isCurrentResidentOf($user, $community->id);
    }

    /**
     * The board and managers see every request; an owner sees their unit's.
     */
    public function view(User $user, ArchitecturalRequest $request): bool
    {
        return $this->allowedFor($user, $request, Permission::ViewGovernance)
            || $request->isSubmittedBy($user)
            || app(VotingRoll::class)->isOwner($user, $request->unit);
    }

    /**
     * Owners submit requests (for units they own — checked again when submitting).
     */
    public function create(User $user, Community $community): bool
    {
        return app(VotingRoll::class)->unitsOwnedBy($user, $community)->isNotEmpty();
    }

    public function decide(User $user, ArchitecturalRequest $request): bool
    {
        return $request->status->isOpen() && $this->allowedFor($user, $request, Permission::ManageGovernance);
    }

    public function withdraw(User $user, ArchitecturalRequest $request): bool
    {
        return $request->status->isOpen() && $request->isSubmittedBy($user);
    }
}

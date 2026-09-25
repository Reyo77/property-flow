<?php

namespace App\Policies;

use App\Enums\MeetingKind;
use App\Enums\Permission;
use App\Models\Community;
use App\Models\Meeting;
use App\Models\User;
use App\Policies\Concerns\ChecksCommunityAccess;
use App\Policies\Concerns\ChecksResidentAccess;

class MeetingPolicy
{
    use ChecksCommunityAccess, ChecksResidentAccess;

    public function viewAny(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ViewGovernance)
            || $this->isCurrentResidentOf($user, $community->id);
    }

    /**
     * Residents see owners' meetings and town halls, not board meetings.
     */
    public function view(User $user, Meeting $meeting): bool
    {
        return $this->allowedFor($user, $meeting, Permission::ViewGovernance)
            || ($meeting->kind !== MeetingKind::Board && $this->isCurrentResidentOf($user, $meeting->community_id));
    }

    public function create(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ManageGovernance);
    }

    public function manage(User $user, Meeting $meeting): bool
    {
        return $this->allowedFor($user, $meeting, Permission::ManageGovernance);
    }

    public function viewMinutes(User $user, Meeting $meeting): bool
    {
        return $this->allowedFor($user, $meeting, Permission::ViewGovernance)
            || ($meeting->hasPublishedMinutes() && $this->view($user, $meeting));
    }
}

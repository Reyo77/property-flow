<?php

namespace App\Policies;

use App\Enums\BallotStatus;
use App\Enums\Permission;
use App\Models\Ballot;
use App\Models\Community;
use App\Models\User;
use App\Policies\Concerns\ChecksCommunityAccess;
use App\Policies\Concerns\ChecksResidentAccess;

class BallotPolicy
{
    use ChecksCommunityAccess, ChecksResidentAccess;

    public function viewAny(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ViewGovernance)
            || $this->isCurrentResidentOf($user, $community->id);
    }

    /**
     * Staff see drafts too; residents see a ballot once it's published.
     */
    public function view(User $user, Ballot $ballot): bool
    {
        return $this->allowedFor($user, $ballot, Permission::ViewGovernance)
            || ($ballot->published_at !== null && $this->isCurrentResidentOf($user, $ballot->community_id));
    }

    public function create(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ManageGovernance);
    }

    public function update(User $user, Ballot $ballot): bool
    {
        return $ballot->status() === BallotStatus::Draft && $this->allowedFor($user, $ballot, Permission::ManageGovernance);
    }

    public function manage(User $user, Ballot $ballot): bool
    {
        return $this->allowedFor($user, $ballot, Permission::ManageGovernance);
    }

    /**
     * The tally is shown to anyone who can see the ballot, but only once it's locked — nobody
     * sees how a vote is going while it's still open.
     */
    public function viewResults(User $user, Ballot $ballot): bool
    {
        return $ballot->closed_at !== null && $this->view($user, $ballot);
    }
}

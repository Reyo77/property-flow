<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Community;
use App\Models\PatrolRoute;
use App\Models\User;
use App\Policies\Concerns\ChecksCommunityAccess;

class PatrolRoutePolicy
{
    use ChecksCommunityAccess;

    public function viewAny(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ViewPatrols);
    }

    public function view(User $user, PatrolRoute $patrolRoute): bool
    {
        return $this->allowedFor($user, $patrolRoute, Permission::ViewPatrols);
    }

    public function create(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ManagePatrols);
    }

    public function update(User $user, PatrolRoute $patrolRoute): bool
    {
        return $this->allowedFor($user, $patrolRoute, Permission::ManagePatrols);
    }

    public function delete(User $user, PatrolRoute $patrolRoute): bool
    {
        return $this->update($user, $patrolRoute);
    }

    /**
     * Scanning a checkpoint on the route (any staff running the patrol, not just its creator).
     */
    public function scan(User $user, PatrolRoute $patrolRoute): bool
    {
        return $this->update($user, $patrolRoute);
    }
}

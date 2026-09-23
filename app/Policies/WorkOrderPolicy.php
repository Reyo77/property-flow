<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Community;
use App\Models\User;
use App\Models\WorkOrder;
use App\Policies\Concerns\ChecksCommunityAccess;

class WorkOrderPolicy
{
    use ChecksCommunityAccess;

    public function viewAny(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ViewServiceRequests);
    }

    /**
     * Team members with access see any work order; a vendor sees only their own jobs.
     */
    public function view(User $user, WorkOrder $workOrder): bool
    {
        return $this->allowedFor($user, $workOrder, Permission::ViewServiceRequests)
            || $workOrder->isAssignedToVendorUser($user);
    }

    public function create(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ManageServiceRequests);
    }

    /**
     * Reassigning, rescheduling or editing the work order's own details (team only).
     */
    public function update(User $user, WorkOrder $workOrder): bool
    {
        return $this->allowedFor($user, $workOrder, Permission::ManageServiceRequests);
    }

    public function delete(User $user, WorkOrder $workOrder): bool
    {
        return $this->update($user, $workOrder);
    }

    /**
     * Updating progress and completion: the team, or the vendor it is assigned to.
     */
    public function updateProgress(User $user, WorkOrder $workOrder): bool
    {
        return $this->update($user, $workOrder) || $workOrder->isAssignedToVendorUser($user);
    }
}

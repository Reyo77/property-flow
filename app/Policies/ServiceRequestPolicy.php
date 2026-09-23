<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Community;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Policies\Concerns\ChecksCommunityAccess;
use App\Policies\Concerns\ChecksResidentAccess;

class ServiceRequestPolicy
{
    use ChecksCommunityAccess, ChecksResidentAccess;

    public function viewAny(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ViewServiceRequests)
            || $this->isCurrentResidentOf($user, $community->id);
    }

    public function view(User $user, ServiceRequest $serviceRequest): bool
    {
        if ($this->allowedFor($user, $serviceRequest, Permission::ViewServiceRequests)) {
            return true;
        }

        if ($serviceRequest->currentWorkOrder()?->isAssignedToVendorUser($user) === true) {
            return true;
        }

        return $this->belongsToResident($user, $serviceRequest);
    }

    /**
     * Team members log a request on anyone's behalf; residents may only file their own.
     */
    public function create(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ManageServiceRequests)
            || $this->isCurrentResidentOf($user, $community->id);
    }

    public function update(User $user, ServiceRequest $serviceRequest): bool
    {
        return $this->allowedFor($user, $serviceRequest, Permission::ManageServiceRequests);
    }

    public function delete(User $user, ServiceRequest $serviceRequest): bool
    {
        return $this->update($user, $serviceRequest);
    }

    /**
     * Anyone who can see the request may add a resident-visible comment.
     */
    public function comment(User $user, ServiceRequest $serviceRequest): bool
    {
        return $this->view($user, $serviceRequest);
    }

    /**
     * Only staff/managers may add an internal, resident-hidden note.
     */
    public function addInternalComment(User $user, ServiceRequest $serviceRequest): bool
    {
        return $this->allowedFor($user, $serviceRequest, Permission::ViewServiceRequests);
    }

    public function manage(User $user, ServiceRequest $serviceRequest): bool
    {
        return $this->update($user, $serviceRequest);
    }

    private function belongsToResident(User $user, ServiceRequest $serviceRequest): bool
    {
        $resident = $user->resident;

        if ($resident === null) {
            return false;
        }

        if ($serviceRequest->reported_by_resident_id === $resident->id) {
            return true;
        }

        return $serviceRequest->unit_id !== null
            && $resident->residencies()->where('unit_id', $serviceRequest->unit_id)->active()->exists();
    }
}

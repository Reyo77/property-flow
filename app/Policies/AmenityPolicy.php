<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Amenity;
use App\Models\Community;
use App\Models\User;
use App\Policies\Concerns\ChecksCommunityAccess;
use App\Policies\Concerns\ChecksResidentAccess;

class AmenityPolicy
{
    use ChecksCommunityAccess, ChecksResidentAccess;

    public function viewAny(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ViewAmenities)
            || $this->isCurrentResidentOf($user, $community->id);
    }

    public function view(User $user, Amenity $amenity): bool
    {
        return $this->allowedFor($user, $amenity, Permission::ViewAmenities)
            || $this->isCurrentResidentOf($user, $amenity->community_id);
    }

    public function create(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ManageAmenities);
    }

    public function update(User $user, Amenity $amenity): bool
    {
        return $this->allowedFor($user, $amenity, Permission::ManageAmenities);
    }

    public function delete(User $user, Amenity $amenity): bool
    {
        return $this->update($user, $amenity);
    }

    /**
     * Team members with manage access book on anyone's behalf; a resident books for themselves.
     * Narrower than view(), since a view-only role (e.g. board member) shouldn't create bookings.
     */
    public function book(User $user, Amenity $amenity): bool
    {
        return $this->allowedFor($user, $amenity, Permission::ManageAmenities)
            || $this->isCurrentResidentOf($user, $amenity->community_id);
    }
}

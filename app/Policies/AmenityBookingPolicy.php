<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\AmenityBooking;
use App\Models\Community;
use App\Models\User;
use App\Policies\Concerns\ChecksCommunityAccess;
use App\Policies\Concerns\ChecksResidentAccess;

class AmenityBookingPolicy
{
    use ChecksCommunityAccess, ChecksResidentAccess;

    public function viewAny(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ViewAmenities)
            || $this->isCurrentResidentOf($user, $community->id);
    }

    /**
     * Team members with access see any booking; a resident sees only their own.
     */
    public function view(User $user, AmenityBooking $booking): bool
    {
        return $this->allowedFor($user, $booking, Permission::ViewAmenities) || $booking->isOwnedBy($user);
    }

    /**
     * A manager decides on behalf of the company; a resident cancels their own booking.
     */
    public function cancel(User $user, AmenityBooking $booking): bool
    {
        return $this->allowedFor($user, $booking, Permission::ManageAmenities) || $booking->isOwnedBy($user);
    }

    public function decide(User $user, AmenityBooking $booking): bool
    {
        return $this->allowedFor($user, $booking, Permission::ManageAmenities);
    }
}

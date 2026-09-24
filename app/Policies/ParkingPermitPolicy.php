<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Community;
use App\Models\ParkingPermit;
use App\Models\User;
use App\Policies\Concerns\ChecksCommunityAccess;

class ParkingPermitPolicy
{
    use ChecksCommunityAccess;

    public function viewAny(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ViewParkingPermits);
    }

    public function view(User $user, ParkingPermit $parkingPermit): bool
    {
        return $this->allowedFor($user, $parkingPermit, Permission::ViewParkingPermits);
    }

    public function create(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ManageParkingPermits);
    }

    public function delete(User $user, ParkingPermit $parkingPermit): bool
    {
        return $this->allowedFor($user, $parkingPermit, Permission::ManageParkingPermits);
    }
}

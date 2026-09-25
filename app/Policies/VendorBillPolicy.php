<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\VendorBillStatus;
use App\Models\Community;
use App\Models\User;
use App\Models\VendorBill;
use App\Policies\Concerns\ChecksCommunityAccess;

class VendorBillPolicy
{
    use ChecksCommunityAccess;

    public function viewAny(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ViewFinance);
    }

    public function create(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ManageFinance);
    }

    /**
     * Approving (or rejecting) a pending bill: up to the community's limit needs ApproveBills;
     * above it, ApproveLargeBills.
     */
    public function approve(User $user, VendorBill $bill): bool
    {
        if ($bill->status !== VendorBillStatus::Pending || ! $this->allowedFor($user, $bill, Permission::ApproveBills)) {
            return false;
        }

        return ! $bill->needsLargeBillApproval() || $user->hasCompanyPermission(Permission::ApproveLargeBills);
    }

    public function pay(User $user, VendorBill $bill): bool
    {
        return $bill->status === VendorBillStatus::Approved && $this->allowedFor($user, $bill, Permission::ManageFinance);
    }
}

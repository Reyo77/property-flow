<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Community;
use App\Models\Payment;
use App\Models\User;
use App\Policies\Concerns\ChecksCommunityAccess;
use App\Policies\Concerns\ChecksResidentAccess;

class PaymentPolicy
{
    use ChecksCommunityAccess, ChecksResidentAccess;

    public function viewAny(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ViewFinance);
    }

    /**
     * Finance staff see any payment; a resident sees (and can download receipts for) their own unit's.
     */
    public function view(User $user, Payment $payment): bool
    {
        return $this->allowedFor($user, $payment, Permission::ViewFinance)
            || $this->isCurrentResidentOfUnit($user, $payment->unit_id);
    }

    public function create(User $user, Community $community): bool
    {
        return $this->allowedIn($user, $community, Permission::ManageFinance);
    }

    public function reverse(User $user, Payment $payment): bool
    {
        return $this->allowedFor($user, $payment, Permission::ManageFinance);
    }
}

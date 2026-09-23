<?php

namespace App\Actions\Invitations;

use App\Enums\CompanyRole;
use App\Models\Invitation;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Validation\ValidationException;

class InviteVendor
{
    public function __construct(private readonly IssueInvitationLink $issueLink) {}

    /**
     * Invite a vendor to the vendor portal, replacing any earlier invitation link.
     *
     * Vendors get no community assignment: their portal access is limited to the work orders
     * assigned to them, which is checked per work order rather than per community.
     *
     * @throws ValidationException
     */
    public function handle(User $inviter, Vendor $vendor): IssuedInvitation
    {
        if ($vendor->email === null) {
            throw ValidationException::withMessages(['email' => __('Add an email address for this vendor first.')]);
        }

        if (User::query()->where('email', $vendor->email)->exists()) {
            throw ValidationException::withMessages(['email' => __('This email already has an account.')]);
        }

        $invitation = $vendor->invitations()->whereNull('accepted_at')->latest('id')->first() ?? new Invitation([]);

        $invitation->forceFill([
            'company_id' => $vendor->company_id,
            'invited_by_id' => $inviter->id,
            'vendor_id' => $vendor->id,
            'name' => $vendor->name,
            'email' => $vendor->email,
            'role' => CompanyRole::Vendor->value,
            'community_ids' => [],
        ]);

        return $this->issueLink->handle($invitation);
    }
}

<?php

namespace App\Actions\Invitations;

use App\Models\Invitation;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class InviteResident
{
    public function __construct(private readonly IssueInvitationLink $issueLink) {}

    /**
     * Invite a resident to the resident portal, replacing any earlier invitation link.
     *
     * @throws ValidationException
     */
    public function handle(User $inviter, Resident $resident): IssuedInvitation
    {
        if ($resident->email === null) {
            throw ValidationException::withMessages(['email' => __('Add an email address for this resident first.')]);
        }

        if (User::query()->where('email', $resident->email)->exists()) {
            throw ValidationException::withMessages(['email' => __('This email already has an account.')]);
        }

        $invitation = $resident->invitations()->whereNull('accepted_at')->latest('id')->first() ?? new Invitation([]);

        $invitation->forceFill([
            'company_id' => $resident->company_id,
            'invited_by_id' => $inviter->id,
            'resident_id' => $resident->id,
            'name' => $resident->name,
            'email' => $resident->email,
            'role' => null,
            'community_ids' => null,
        ]);

        return $this->issueLink->handle($invitation);
    }
}

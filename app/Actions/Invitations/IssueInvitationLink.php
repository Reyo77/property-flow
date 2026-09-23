<?php

namespace App\Actions\Invitations;

use App\Models\Invitation;
use Illuminate\Support\Str;

/**
 * Gives an invitation a fresh link and expiry. Any earlier link stops working.
 */
class IssueInvitationLink
{
    public function handle(Invitation $invitation): IssuedInvitation
    {
        $token = Str::random(48);

        $invitation->forceFill([
            'token_hash' => Invitation::hashToken($token),
            'expires_at' => now()->addDays(Invitation::EXPIRES_AFTER_DAYS),
        ])->save();

        return new IssuedInvitation($invitation, route('invitations.accept', $token));
    }
}

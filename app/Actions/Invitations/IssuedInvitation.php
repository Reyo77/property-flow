<?php

namespace App\Actions\Invitations;

use App\Models\Invitation;

/**
 * A new invitation with the link to share. The link cannot be shown again later.
 */
final readonly class IssuedInvitation
{
    public function __construct(
        public Invitation $invitation,
        public string $url,
    ) {}
}

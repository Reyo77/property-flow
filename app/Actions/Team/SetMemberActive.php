<?php

namespace App\Actions\Team;

use App\Models\User;

class SetMemberActive
{
    /**
     * Deactivate a member (they can no longer sign in) or reactivate them.
     */
    public function handle(User $actor, User $member, bool $active): void
    {
        $member->forceFill(['deactivated_at' => $active ? null : now()])->save();

        if (! $active) {
            SignOutEverywhere::for($member);
        }

        activity()
            ->performedOn($member)
            ->causedBy($actor)
            ->event($active ? 'reactivated' : 'deactivated')
            ->log($active ? 'Member reactivated' : 'Member deactivated');
    }
}

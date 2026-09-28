<?php

namespace App\Actions\Platform;

use App\Actions\Team\SignOutEverywhere;
use App\Models\Company;
use App\Models\User;

/**
 * Suspending signs every one of the company's users out immediately (deactivation-style); their
 * data stays intact and reactivating restores access without needing to be re-invited.
 */
class SetCompanySuspended
{
    public function handle(User $actor, Company $company, bool $suspended): void
    {
        $company->forceFill(['suspended_at' => $suspended ? now() : null])->save();

        if ($suspended) {
            foreach ($company->users as $user) {
                SignOutEverywhere::for($user);
            }
        }

        activity()
            ->causedBy($actor)
            ->performedOn($company)
            ->event($suspended ? 'suspended' : 'reactivated')
            ->log($suspended ? 'Company suspended' : 'Company reactivated');
    }
}

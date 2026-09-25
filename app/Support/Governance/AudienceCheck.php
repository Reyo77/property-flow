<?php

namespace App\Support\Governance;

use App\Enums\Audience;
use App\Enums\ResidencyType;
use App\Models\Residency;
use App\Models\User;

/**
 * Whether someone is in a survey's or consent form's audience: a current resident of the
 * community, or for owners-only, a current owner there.
 */
class AudienceCheck
{
    public function includes(User $user, int $communityId, Audience $audience): bool
    {
        $resident = $user->loadMissing('resident')->resident;

        if ($resident === null) {
            return false;
        }

        return Residency::query()->withoutGlobalScopes()
            ->where('community_id', $communityId)
            ->where('resident_id', $resident->id)
            ->when($audience === Audience::Owners, fn ($query) => $query->where('type', ResidencyType::Owner))
            ->active()
            ->exists();
    }
}

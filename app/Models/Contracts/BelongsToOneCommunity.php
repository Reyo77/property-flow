<?php

namespace App\Models\Contracts;

use App\Policies\Concerns\ChecksCommunityAccess;

/**
 * A company-scoped model that also belongs to exactly one community, so policies can check
 * community access generically (see {@see ChecksCommunityAccess::allowedFor()}).
 */
interface BelongsToOneCommunity
{
    public function companyIdForAccessCheck(): int;

    public function communityIdForAccessCheck(): int;
}

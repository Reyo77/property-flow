<?php

namespace App\Models\Concerns;

use App\Models\Contracts\BelongsToOneCommunity;

/**
 * Implements {@see BelongsToOneCommunity} for a model that has real
 * `company_id` and `community_id` columns.
 */
trait BelongsToCommunity
{
    public function companyIdForAccessCheck(): int
    {
        return $this->company_id;
    }

    public function communityIdForAccessCheck(): int
    {
        return $this->community_id;
    }
}

<?php

namespace App\Policies\Concerns;

use App\Enums\CompanyRole;
use App\Enums\DocumentVisibility;
use App\Enums\Permission;
use App\Models\User;

trait ChecksDocumentVisibility
{
    /**
     * Team roles that count as "board & managers" for {@see DocumentVisibility::Board}.
     */
    private const array BOARD_ROLES = [CompanyRole::CompanyAdmin, CompanyRole::PropertyManager, CompanyRole::BoardMember];

    /**
     * Whether the user (team member or resident) may see content at this visibility level, in this community.
     */
    protected function canSeeVisibility(User $user, DocumentVisibility $visibility, int $companyId, int $communityId): bool
    {
        if ($user->canAccessCommunityById($companyId, $communityId) && $user->hasCompanyPermission(Permission::ViewDocuments)) {
            if ($visibility !== DocumentVisibility::Board) {
                return true;
            }

            $role = CompanyRole::tryFrom((string) $user->companyRoleName());

            return $role !== null && in_array($role, self::BOARD_ROLES, true);
        }

        if ($visibility === DocumentVisibility::Board || $visibility === DocumentVisibility::Staff) {
            return false;
        }

        $residency = $user->resident?->residencies()->where('community_id', $communityId)->active()->first();

        return $residency !== null && $visibility->visibleToResidencyType($residency->type);
    }
}

<?php

namespace App\Enums;

/**
 * Who can see a document or folder, from most to least open.
 *
 * There is no public website yet (Phase 10), so "public" currently behaves the same as "residents"
 * for anyone signed in; it marks documents that will be shown on the community website later.
 */
enum DocumentVisibility: string
{
    case Public = 'public';
    case Residents = 'residents';
    case Owners = 'owners';
    case Board = 'board';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Public => __('Public'),
            self::Residents => __('Residents'),
            self::Owners => __('Owners only'),
            self::Board => __('Board & managers'),
            self::Staff => __('Staff only'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Public => __('Everyone, including the future public website'),
            self::Residents => __('All residents (owners, tenants and occupants) and the team'),
            self::Owners => __('Owners and the team, not tenants'),
            self::Board => __('Board members, managers and admins only'),
            self::Staff => __('Team members only, no residents'),
        };
    }

    /**
     * Whether a resident of the given type can see content at this visibility level.
     */
    public function visibleToResidencyType(ResidencyType $type): bool
    {
        return match ($this) {
            self::Public, self::Residents => true,
            self::Owners => $type === ResidencyType::Owner,
            self::Board, self::Staff => false,
        };
    }
}

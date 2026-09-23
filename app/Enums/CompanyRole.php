<?php

namespace App\Enums;

enum CompanyRole: string
{
    case CompanyAdmin = 'company-admin';

    public function label(): string
    {
        return match ($this) {
            self::CompanyAdmin => __('Company admin'),
        };
    }

    /**
     * The permissions every company grants this role by default.
     *
     * @return list<Permission>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::CompanyAdmin => Permission::cases(),
        };
    }
}

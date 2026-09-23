<?php

namespace App\Support\Tenancy;

use Closure;

/**
 * Runs role and permission work against a specific company's roles.
 */
class PermissionTeam
{
    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function run(int $companyId, Closure $callback): mixed
    {
        $previousTeamId = getPermissionsTeamId();

        setPermissionsTeamId($companyId);

        try {
            return $callback();
        } finally {
            setPermissionsTeamId($previousTeamId);
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Actions\Companies\SyncDefaultRoles;
use Illuminate\Console\Command;

/**
 * Grants permissions introduced by a release to every existing company's default roles.
 * Run on each deploy, after migrating.
 */
class SyncNewPermissionsCommand extends Command
{
    protected $signature = 'permissions:sync-new';

    protected $description = 'Create newly added permissions and grant them to existing companies\' default roles';

    public function handle(SyncDefaultRoles $syncDefaultRoles): int
    {
        $created = $syncDefaultRoles->grantNewPermissions();

        $this->info(count($created).' new permission(s)'.($created === [] ? '.' : ': '.implode(', ', $created)));

        return self::SUCCESS;
    }
}

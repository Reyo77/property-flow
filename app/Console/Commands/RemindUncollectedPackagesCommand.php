<?php

namespace App\Console\Commands;

use App\Actions\FrontDesk\RemindUncollectedPackages;
use Illuminate\Console\Command;

/**
 * Re-notifies residents about packages that have sat uncollected for a few days.
 *
 * Runs daily via the scheduler (see routes/console.php).
 */
class RemindUncollectedPackagesCommand extends Command
{
    protected $signature = 'packages:remind-uncollected';

    protected $description = 'Remind residents about packages still awaiting pickup';

    public function handle(RemindUncollectedPackages $remindUncollectedPackages): int
    {
        $count = $remindUncollectedPackages->handle();

        $this->info("Sent {$count} pickup reminder(s).");

        return self::SUCCESS;
    }
}

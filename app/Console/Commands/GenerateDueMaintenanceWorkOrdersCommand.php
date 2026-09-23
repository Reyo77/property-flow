<?php

namespace App\Console\Commands;

use App\Actions\Maintenance\GenerateDueMaintenanceWorkOrders;
use Illuminate\Console\Command;

/**
 * Turns every due preventive-maintenance schedule into a work order.
 *
 * Runs daily via the scheduler (see routes/console.php).
 */
class GenerateDueMaintenanceWorkOrdersCommand extends Command
{
    protected $signature = 'maintenance:generate-due-work-orders';

    protected $description = 'Generate work orders for maintenance schedules that have come due';

    public function handle(GenerateDueMaintenanceWorkOrders $generateDueMaintenanceWorkOrders): int
    {
        $count = $generateDueMaintenanceWorkOrders->handle();

        $this->info("Generated {$count} work order(s) from due maintenance schedules.");

        return self::SUCCESS;
    }
}

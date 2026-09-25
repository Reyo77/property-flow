<?php

namespace App\Console\Commands;

use App\Actions\Finance\SendOverdueReminders;
use App\Models\Community;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Reminds residents about overdue invoices. Runs daily via the scheduler (see routes/console.php).
 */
class SendOverdueRemindersCommand extends Command
{
    protected $signature = 'finance:send-overdue-reminders';

    protected $description = 'Remind residents about overdue invoices';

    public function handle(SendOverdueReminders $sendOverdueReminders): int
    {
        $communityIds = Invoice::query()->withoutGlobalScopes()->whereNull('voided_at')->whereDate('due_on', '<', now()->toDateString())->distinct()->pluck('community_id');
        $reminded = 0;

        Community::query()->withoutGlobalScopes()->whereIn('id', $communityIds)->orderBy('id')->each(function (Community $community) use ($sendOverdueReminders, &$reminded): void {
            $reminded += $sendOverdueReminders->handle($community, CarbonImmutable::now());
        });

        $this->info("Sent reminders for {$reminded} invoice(s).");

        return self::SUCCESS;
    }
}

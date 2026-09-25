<?php

namespace App\Console\Commands;

use App\Actions\Finance\RunBilling;
use App\Models\Community;
use App\Models\RecurringCharge;
use App\Support\Tenancy\CompanyScope;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Bills every community's recurring charges for the month. Runs daily via the scheduler (see
 * routes/console.php): the first run of a month issues the invoices and later runs find nothing
 * left to do, so a missed day or a crash part-way through is caught up automatically.
 */
class RunBillingCommand extends Command
{
    protected $signature = 'finance:run-billing {--month= : The month to bill, as YYYY-MM (default: the current month in each community\'s time zone)}';

    protected $description = 'Issue this month\'s invoices for recurring charges';

    public function handle(RunBilling $runBilling): int
    {
        $month = $this->option('month');

        if (is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month) !== 1) {
            $this->error('The month must look like 2026-10.');

            return self::INVALID;
        }

        $communityIds = RecurringCharge::query()->withoutGlobalScopes()->where('is_active', true)->distinct()->pluck('community_id');
        $issued = 0;

        Community::query()->withoutGlobalScope(CompanyScope::class)->whereIn('id', $communityIds)->orderBy('id')->each(function (Community $community) use ($runBilling, $month, &$issued): void {
            $billedMonth = is_string($month)
                ? CarbonImmutable::createFromFormat('Y-m-d', "{$month}-01", $community->timezone)
                : CarbonImmutable::now($community->timezone);

            if (! $billedMonth instanceof CarbonImmutable) {
                return;
            }

            $result = $runBilling->handle($community, $billedMonth->startOfMonth());
            $issued += $result->issued;

            if ($result->unitsWithoutFactor > 0) {
                $this->warn("{$community->name}: {$result->unitsWithoutFactor} unit(s) have no unit factor and were left out of factor-based charges.");
            }
        });

        $this->info("Issued {$issued} invoice(s).");

        return self::SUCCESS;
    }
}

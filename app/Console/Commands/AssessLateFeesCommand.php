<?php

namespace App\Console\Commands;

use App\Actions\Finance\AssessLateFees;
use App\Models\Community;
use App\Models\LateFeeRule;
use App\Support\Tenancy\CompanyScope;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Charges late fees on invoices past their grace period. Runs daily via the scheduler (see
 * routes/console.php); each invoice is only ever charged once.
 */
class AssessLateFeesCommand extends Command
{
    protected $signature = 'finance:assess-late-fees';

    protected $description = 'Charge late fees on overdue invoices';

    public function handle(AssessLateFees $assessLateFees): int
    {
        $communityIds = LateFeeRule::query()->withoutGlobalScopes()->where('is_active', true)->pluck('community_id');
        $charged = 0;

        Community::query()->withoutGlobalScope(CompanyScope::class)->whereIn('id', $communityIds)->orderBy('id')->each(function (Community $community) use ($assessLateFees, &$charged): void {
            $charged += $assessLateFees->handle($community, CarbonImmutable::now($community->timezone));
        });

        $this->info("Charged {$charged} late fee(s).");

        return self::SUCCESS;
    }
}

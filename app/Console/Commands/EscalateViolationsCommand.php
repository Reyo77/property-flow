<?php

namespace App\Console\Commands;

use App\Actions\Violations\EscalateViolation;
use App\Enums\ViolationStatus;
use App\Models\Violation;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Moves every open violation whose cure period has run out to its next step. Runs daily via
 * the scheduler (see routes/console.php).
 */
class EscalateViolationsCommand extends Command
{
    protected $signature = 'violations:escalate';

    protected $description = 'Escalate bylaw violations whose cure period has passed';

    public function handle(EscalateViolation $escalateViolation): int
    {
        $escalated = 0;

        Violation::query()->withoutGlobalScopes()
            ->where('status', ViolationStatus::Open)
            ->whereNotNull('next_action_on')
            ->whereDate('next_action_on', '<=', now()->addDay()->toDateString())
            ->with('community')
            ->each(function (Violation $violation) use ($escalateViolation, &$escalated): void {
                if ($escalateViolation->handle($violation, CarbonImmutable::now($violation->community->timezone)->startOfDay()) !== null) {
                    $escalated++;
                }
            });

        $this->info("Escalated {$escalated} violation(s).");

        return self::SUCCESS;
    }
}

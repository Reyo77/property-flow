<?php

namespace App\Console\Commands;

use App\Actions\Governance\CloseBallot;
use App\Models\Ballot;
use Illuminate\Console\Command;

/**
 * Locks the results of every published ballot whose voting time has run out. Runs every
 * minute via the scheduler (see routes/console.php).
 */
class CloseEndedBallotsCommand extends Command
{
    protected $signature = 'ballots:close-ended';

    protected $description = 'Count and lock ballots whose voting has ended';

    public function handle(CloseBallot $closeBallot): int
    {
        $closed = 0;

        Ballot::query()->withoutGlobalScopes()
            ->whereNotNull('published_at')
            ->whereNull('closed_at')
            ->where('closes_at', '<=', now())
            ->each(function (Ballot $ballot) use ($closeBallot, &$closed): void {
                $closeBallot->handle($ballot);
                $closed++;
            });

        $this->info("Closed {$closed} ballot(s).");

        return self::SUCCESS;
    }
}

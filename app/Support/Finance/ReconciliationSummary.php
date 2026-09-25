<?php

namespace App\Support\Finance;

use App\Models\BankStatementLine;
use App\Models\LedgerEntry;
use Illuminate\Support\Collection;

/**
 * Where a bank statement stands against the books.
 *
 * The bank should show the book balance, minus what the books have that the bank hasn't cleared
 * yet (outstanding cheques and deposits), plus what the bank has that isn't in the books yet
 * (unrecorded bank lines, e.g. a service charge). `difference` is how far the statement's
 * closing balance is from that; it must be zero, with every bank line recorded, to reconcile.
 * Book activity from before the first statement imported counts as cleared.
 */
final readonly class ReconciliationSummary
{
    /**
     * @param  Collection<int, LedgerEntry>  $outstanding
     * @param  Collection<int, BankStatementLine>  $unmatchedLines
     */
    public function __construct(
        public Money $statementBalance,
        public Money $bookBalance,
        public Collection $outstanding,
        public Money $outstandingTotal,
        public Collection $unmatchedLines,
        public Money $unmatchedTotal,
        public Money $difference,
    ) {}

    public function canComplete(): bool
    {
        return $this->difference->isZero() && $this->unmatchedLines->isEmpty();
    }
}

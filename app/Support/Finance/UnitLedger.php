<?php

namespace App\Support\Finance;

use App\Enums\SystemAccount;
use App\Models\LedgerEntry;
use App\Models\Unit;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * A unit's account with the community: every receivable line posted for it, with a running
 * balance. Positive balance = the unit owes money; negative = it has a credit.
 */
class UnitLedger
{
    public function __construct(private readonly ChartOfAccounts $chartOfAccounts) {}

    public function balance(Unit $unit, ?CarbonInterface $asOf = null): Money
    {
        $query = $this->lines($unit);

        if ($asOf !== null) {
            $query->whereDate('posted_on', '<=', $asOf->toDateString());
        }

        $totals = $query->toBase()->selectRaw('COALESCE(SUM(debit_cents), 0) as debits, COALESCE(SUM(credit_cents), 0) as credits')->first();

        return Money::of((int) ($totals->debits ?? 0) - (int) ($totals->credits ?? 0), $unit->community->currency);
    }

    /**
     * @return Collection<int, array{entry: LedgerEntry, balance: Money}>
     */
    public function statement(Unit $unit, ?CarbonInterface $from = null, ?CarbonInterface $to = null): Collection
    {
        $opening = $from === null ? Money::zero($unit->community->currency) : $this->balance($unit, $from->copy()->subDay());

        $query = $this->lines($unit)->with('journalEntry')->orderBy('posted_on')->orderBy('id');

        if ($from !== null) {
            $query->whereDate('posted_on', '>=', $from->toDateString());
        }

        if ($to !== null) {
            $query->whereDate('posted_on', '<=', $to->toDateString());
        }

        $running = $opening;
        $rows = [];

        foreach ($query->get() as $entry) {
            $running = $running->plus(Money::of($entry->netCents(), $running->currency));
            $rows[] = ['entry' => $entry, 'balance' => $running];
        }

        return collect($rows);
    }

    /**
     * @return Builder<LedgerEntry>
     */
    private function lines(Unit $unit): Builder
    {
        return LedgerEntry::query()->withoutGlobalScopes()
            ->where('unit_id', $unit->id)
            ->where('account_id', $this->chartOfAccounts->account($unit->community, SystemAccount::Receivables)->id);
    }
}

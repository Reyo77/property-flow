<?php

namespace App\Support\Finance;

use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\LedgerEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Matching a statement's lines to ledger lines on the bank account, and the summary that says
 * whether the two agree.
 */
class BankReconciliation
{
    /**
     * How far apart the bank's date and the book date can be for an automatic match (cheques
     * take a few days to clear).
     */
    public const int MATCH_WINDOW_DAYS = 5;

    /**
     * Matches every unmatched line that has exactly the right amount on the bank account within
     * the date window, taking the closest date first. Returns how many it matched.
     */
    public function autoMatch(BankStatement $statement): int
    {
        $this->ensureOpen($statement);

        return DB::transaction(function () use ($statement): int {
            $matched = 0;

            foreach ($statement->lines()->whereNull('ledger_entry_id')->orderBy('posted_on')->orderBy('id')->get() as $line) {
                $candidate = $this->candidates($statement, $line)->first();

                if ($candidate !== null) {
                    $line->forceFill(['ledger_entry_id' => $candidate->id])->save();
                    $matched++;
                }
            }

            return $matched;
        });
    }

    /**
     * Unmatched ledger lines on the statement's account with this line's amount: one carrying
     * the bank line's reference (a cheque or transfer number) first, then the closest date.
     *
     * @return Collection<int, LedgerEntry>
     */
    public function candidates(BankStatement $statement, BankStatementLine $line, int $windowDays = self::MATCH_WINDOW_DAYS): Collection
    {
        $date = CarbonImmutable::parse($line->posted_on->toDateString());

        return $this->unmatchedBookLines($statement)
            ->whereRaw('CAST(debit_cents AS SIGNED) - CAST(credit_cents AS SIGNED) = ?', [$line->amount_cents])
            ->whereBetween('posted_on', [$date->subDays($windowDays)->toDateString(), $date->addDays($windowDays)->toDateString()])
            ->with('journalEntry')
            ->when($line->reference !== null, fn (Builder $query) => $query->orderByRaw('CASE WHEN memo = ? THEN 0 ELSE 1 END', [$line->reference]))
            ->orderByRaw('ABS(DATEDIFF(posted_on, ?))', [$date->toDateString()])
            ->orderBy('id')
            ->get();
    }

    /**
     * @throws LogicException when the ledger line can't be this bank line
     */
    public function match(BankStatementLine $line, LedgerEntry $entry): void
    {
        $statement = $line->bankStatement;
        $this->ensureOpen($statement);

        if ($entry->account_id !== $statement->account_id) {
            throw new LogicException(__('That entry is not on this bank account.'));
        }

        if ($entry->netCents() !== $line->amount_cents) {
            throw new LogicException(__('The amounts do not match.'));
        }

        if (BankStatementLine::query()->withoutGlobalScopes()->where('ledger_entry_id', $entry->id)->whereKeyNot($line->id)->exists()) {
            throw new LogicException(__('That entry is already matched to another bank line.'));
        }

        $line->forceFill(['ledger_entry_id' => $entry->id])->save();
    }

    public function unmatch(BankStatementLine $line): void
    {
        $this->ensureOpen($line->bankStatement);

        $line->forceFill(['ledger_entry_id' => null])->save();
    }

    public function summary(BankStatement $statement): ReconciliationSummary
    {
        $currency = $statement->community->currency;
        $endsOn = $statement->ends_on->toDateString();

        $bookBalance = (int) LedgerEntry::query()->withoutGlobalScopes()
            ->where('account_id', $statement->account_id)
            ->whereDate('posted_on', '<=', $endsOn)
            ->toBase()
            ->selectRaw('COALESCE(SUM(CAST(debit_cents AS SIGNED) - CAST(credit_cents AS SIGNED)), 0) as balance')
            ->value('balance');

        // Reconciliation starts with the first statement imported for the account: book activity
        // before it has no statement to appear on and is taken as already cleared.
        $startedOn = BankStatement::query()->withoutGlobalScopes()->where('account_id', $statement->account_id)->min('starts_on');

        $outstanding = $this->unmatchedBookLines($statement)
            ->whereDate('posted_on', '<=', $endsOn)
            ->when(is_string($startedOn), fn (Builder $query) => $query->whereDate('posted_on', '>=', (string) $startedOn))
            ->with('journalEntry')->orderBy('posted_on')->orderBy('id')->get();
        $outstandingTotal = (int) $outstanding->sum(fn (LedgerEntry $entry) => $entry->netCents());

        $unmatchedLines = $statement->lines()->whereNull('ledger_entry_id')->orderBy('posted_on')->orderBy('id')->get();
        $unmatchedTotal = (int) $unmatchedLines->sum('amount_cents');

        $expectedBankBalance = $bookBalance - $outstandingTotal + $unmatchedTotal;

        return new ReconciliationSummary(
            Money::of($statement->closing_balance_cents, $currency),
            Money::of($bookBalance, $currency),
            $outstanding,
            Money::of($outstandingTotal, $currency),
            $unmatchedLines,
            Money::of($unmatchedTotal, $currency),
            Money::of($statement->closing_balance_cents - $expectedBankBalance, $currency),
        );
    }

    /**
     * Ledger lines on the bank account that no statement line (on any statement) has claimed.
     *
     * @return Builder<LedgerEntry>
     */
    private function unmatchedBookLines(BankStatement $statement): Builder
    {
        return LedgerEntry::query()->withoutGlobalScopes()
            ->where('account_id', $statement->account_id)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('bank_statement_lines')->whereColumn('bank_statement_lines.ledger_entry_id', 'ledger_entries.id'));
    }

    private function ensureOpen(BankStatement $statement): void
    {
        if ($statement->isReconciled()) {
            throw new LogicException(__('This statement is already reconciled.'));
        }
    }
}

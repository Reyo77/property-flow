<?php

namespace App\Actions\Finance;

use App\Models\Community;
use App\Models\JournalEntry;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Support\Finance\FiscalYears;
use App\Support\Finance\JournalLine;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * The single way anything reaches the ledger. Every entry is checked to balance (total debits
 * equal total credits), to use only this community's active accounts, and to land in an open
 * fiscal year — before a single row is written.
 */
class PostJournalEntry
{
    public function __construct(private readonly FiscalYears $fiscalYears) {}

    /**
     * @param  list<JournalLine>  $lines
     *
     * @throws InvalidArgumentException when the lines don't balance or reference the wrong accounts
     * @throws LogicException when the date falls in a closed fiscal year
     */
    public function handle(
        Community $community,
        CarbonInterface $postedOn,
        string $memo,
        array $lines,
        ?Model $source = null,
        ?User $postedBy = null,
        ?JournalEntry $reverses = null,
    ): JournalEntry {
        $this->assertValid($community, $lines, allowInactiveAccounts: $reverses !== null);

        return DB::transaction(function () use ($community, $postedOn, $memo, $lines, $source, $postedBy, $reverses): JournalEntry {
            $fiscalYear = $this->fiscalYears->covering($community, $postedOn);

            if ($fiscalYear->isClosed()) {
                throw new LogicException(__('The :year fiscal year is closed; nothing more can be posted into it.', ['year' => $fiscalYear->label()]));
            }

            $entry = new JournalEntry;
            $entry->forceFill([
                'company_id' => $community->company_id,
                'community_id' => $community->id,
                'fiscal_year_id' => $fiscalYear->id,
                'posted_on' => $postedOn->toDateString(),
                'memo' => $memo,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'reverses_id' => $reverses?->id,
                'created_by_id' => $postedBy?->id,
            ])->save();

            foreach ($lines as $line) {
                $ledgerEntry = new LedgerEntry;
                $ledgerEntry->forceFill([
                    'company_id' => $community->company_id,
                    'community_id' => $community->id,
                    'journal_entry_id' => $entry->id,
                    'account_id' => $line->account->id,
                    'unit_id' => $line->unitId,
                    'posted_on' => $postedOn->toDateString(),
                    'debit_cents' => $line->debitCents,
                    'credit_cents' => $line->creditCents,
                    'memo' => $line->memo,
                ])->save();
            }

            return $entry;
        });
    }

    /**
     * @param  list<JournalLine>  $lines
     */
    private function assertValid(Community $community, array $lines, bool $allowInactiveAccounts): void
    {
        if (count($lines) < 2) {
            throw new InvalidArgumentException('A journal entry needs at least two lines.');
        }

        $debits = 0;
        $credits = 0;

        foreach ($lines as $line) {
            if ($line->account->community_id !== $community->id) {
                throw new InvalidArgumentException("Account {$line->account->code} belongs to another community.");
            }

            // A reversal may touch an account deactivated since the original posting.
            if (! $allowInactiveAccounts && ! $line->account->is_active) {
                throw new InvalidArgumentException("Account {$line->account->code} is inactive.");
            }

            if (($line->debitCents > 0) === ($line->creditCents > 0) || $line->debitCents < 0 || $line->creditCents < 0) {
                throw new InvalidArgumentException('Each journal line must be either a positive debit or a positive credit.');
            }

            $debits += $line->debitCents;
            $credits += $line->creditCents;
        }

        if ($debits !== $credits) {
            throw new InvalidArgumentException("Journal entry does not balance: debits {$debits} ≠ credits {$credits}.");
        }
    }
}

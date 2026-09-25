<?php

namespace App\Actions\Finance;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\BankStatementLine;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Support\Finance\BankReconciliation;
use App\Support\Finance\JournalLine;
use App\Support\Finance\Money;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Books a bank line the ledger doesn't have yet — a service charge, interest earned — against
 * an income or expense account, and matches the line to the new entry.
 */
class RecordBankLine
{
    public function __construct(
        private readonly PostJournalEntry $postJournalEntry,
        private readonly BankReconciliation $bankReconciliation,
    ) {}

    /**
     * @throws LogicException
     */
    public function handle(BankStatementLine $line, Account $counterAccount, User $recordedBy): LedgerEntry
    {
        $statement = $line->bankStatement;

        if ($line->isMatched()) {
            throw new LogicException(__('This bank line is already matched.'));
        }

        if ($counterAccount->community_id !== $statement->community_id || ! $counterAccount->is_active
            || ! in_array($counterAccount->type, [AccountType::Income, AccountType::Expense], true)) {
            throw new LogicException(__('Choose an active income or expense account for this line.'));
        }

        return DB::transaction(function () use ($line, $statement, $counterAccount, $recordedBy): LedgerEntry {
            $community = $statement->community;
            $amount = Money::of(abs($line->amount_cents), $community->currency);
            $bank = $statement->account;
            $isDeposit = $line->amount_cents > 0;

            $entry = $this->postJournalEntry->handle(
                $community,
                $line->posted_on,
                __('Bank: :description', ['description' => $line->description]),
                $isDeposit
                    ? [JournalLine::debit($bank, $amount, memo: $line->reference), JournalLine::credit($counterAccount, $amount)]
                    : [JournalLine::debit($counterAccount, $amount), JournalLine::credit($bank, $amount, memo: $line->reference)],
                $statement,
                $recordedBy,
            );

            $bankLine = $entry->lines()->where('account_id', $bank->id)->sole();
            $this->bankReconciliation->match($line, $bankLine);

            return $bankLine;
        });
    }
}

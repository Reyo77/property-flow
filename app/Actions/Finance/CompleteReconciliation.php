<?php

namespace App\Actions\Finance;

use App\Models\BankStatement;
use App\Models\User;
use App\Support\Finance\BankReconciliation;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Signs off a statement once the books and the bank agree. Its matches are then locked.
 */
class CompleteReconciliation
{
    public function __construct(private readonly BankReconciliation $bankReconciliation) {}

    /**
     * @throws LogicException
     */
    public function handle(BankStatement $statement, User $reconciledBy): void
    {
        DB::transaction(function () use ($statement, $reconciledBy): void {
            $statement = BankStatement::query()->withoutGlobalScopes()->whereKey($statement->id)->lockForUpdate()->firstOrFail();

            if ($statement->isReconciled()) {
                throw new LogicException(__('This statement is already reconciled.'));
            }

            $summary = $this->bankReconciliation->summary($statement);

            if (! $summary->canComplete()) {
                throw new LogicException(__('The statement does not reconcile yet: :difference difference, :count unrecorded bank line(s).', [
                    'difference' => $summary->difference->format(),
                    'count' => $summary->unmatchedLines->count(),
                ]));
            }

            $statement->forceFill(['reconciled_at' => now(), 'reconciled_by_id' => $reconciledBy->id])->save();
        });
    }
}

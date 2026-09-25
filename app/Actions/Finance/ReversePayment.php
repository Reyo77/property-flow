<?php

namespace App\Actions\Finance;

use App\Enums\PaymentReversalReason;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Undoes a payment — refunded to the resident, returned by the bank (NSF), or recorded in
 * error — by reversing its ledger posting. The invoices it had paid reopen, and any other
 * credit the unit has is re-applied to them.
 */
class ReversePayment
{
    public function __construct(
        private readonly ReverseJournalEntry $reverseJournalEntry,
        private readonly AllocateUnitCredits $allocateUnitCredits,
    ) {}

    /**
     * @throws LogicException
     */
    public function handle(Payment $payment, PaymentReversalReason $reason, CarbonInterface $reversedOn, ?User $reversedBy = null): void
    {
        DB::transaction(function () use ($payment, $reason, $reversedOn, $reversedBy): void {
            $payment = Payment::query()->withoutGlobalScopes()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->isReversed()) {
                throw new LogicException(__('This payment has already been reversed.'));
            }

            $entry = $payment->journal_entry_id === null ? null : JournalEntry::query()->withoutGlobalScopes()->find($payment->journal_entry_id);

            if ($entry === null) {
                throw new LogicException('Payment has no ledger posting to reverse.');
            }

            $reversal = $this->reverseJournalEntry->handle(
                $entry,
                $reversedOn,
                __(':number :reason', ['number' => $payment->displayNumber(), 'reason' => $reason->label()]),
                $reversedBy,
            );

            $payment->forceFill([
                'reversed_at' => now(),
                'reversal_reason' => $reason,
                'reversal_journal_entry_id' => $reversal->id,
                'reversed_by_id' => $reversedBy?->id,
            ])->save();

            $unit = $payment->unit()->withoutGlobalScopes()->firstOrFail();
            $this->allocateUnitCredits->handle($unit);
        });
    }
}

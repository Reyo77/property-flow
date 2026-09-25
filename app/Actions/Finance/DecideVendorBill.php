<?php

namespace App\Actions\Finance;

use App\Enums\SystemAccount;
use App\Enums\VendorBillStatus;
use App\Models\User;
use App\Models\VendorBill;
use App\Support\Finance\ChartOfAccounts;
use App\Support\Finance\JournalLine;
use App\Support\Finance\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use LogicException;

/**
 * Approves or rejects a pending bill. Approval posts the expense and the amount owed to the
 * vendor (Dr expense, Cr accounts payable) on the bill's date.
 *
 * Who may approve depends on the amount: a manager up to the community's approval limit, the
 * board above it. That rule is checked here as well as in the UI, so no caller can skip it.
 */
class DecideVendorBill
{
    public function __construct(
        private readonly PostJournalEntry $postJournalEntry,
        private readonly ChartOfAccounts $chartOfAccounts,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws LogicException
     */
    public function approve(VendorBill $bill, User $approver, ?string $notes = null): void
    {
        Gate::forUser($approver)->authorize('approve', $bill);

        DB::transaction(function () use ($bill, $approver, $notes): void {
            $bill = $this->lockPending($bill);
            $community = $bill->community;
            $amount = Money::of($bill->amount_cents, $community->currency);

            $entry = $this->postJournalEntry->handle(
                $community,
                $bill->billed_on,
                __(':number · :vendor: :description', ['number' => $bill->displayNumber(), 'vendor' => $bill->vendor->name, 'description' => $bill->description]),
                [
                    JournalLine::debit($bill->account, $amount),
                    JournalLine::credit($this->chartOfAccounts->account($community, SystemAccount::Payables), $amount, memo: $bill->vendor->name),
                ],
                $bill,
                $approver,
            );

            $bill->forceFill([
                'status' => VendorBillStatus::Approved,
                'decided_by_id' => $approver->id,
                'decided_at' => now(),
                'decision_notes' => $notes,
                'approval_journal_entry_id' => $entry->id,
            ])->save();
        });
    }

    /**
     * @throws AuthorizationException
     * @throws LogicException
     */
    public function reject(VendorBill $bill, User $approver, string $reason): void
    {
        Gate::forUser($approver)->authorize('approve', $bill);

        DB::transaction(function () use ($bill, $approver, $reason): void {
            $bill = $this->lockPending($bill);

            $bill->forceFill([
                'status' => VendorBillStatus::Rejected,
                'decided_by_id' => $approver->id,
                'decided_at' => now(),
                'decision_notes' => $reason,
            ])->save();
        });
    }

    private function lockPending(VendorBill $bill): VendorBill
    {
        $bill = VendorBill::query()->withoutGlobalScopes()->whereKey($bill->id)->lockForUpdate()->with(['community', 'vendor', 'account'])->firstOrFail();

        if ($bill->status !== VendorBillStatus::Pending) {
            throw new LogicException(__('This bill has already been decided.'));
        }

        return $bill;
    }
}

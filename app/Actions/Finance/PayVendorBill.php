<?php

namespace App\Actions\Finance;

use App\Enums\PaymentMethod;
use App\Enums\SystemAccount;
use App\Enums\VendorBillStatus;
use App\Models\User;
use App\Models\VendorBill;
use App\Support\Finance\ChartOfAccounts;
use App\Support\Finance\JournalLine;
use App\Support\Finance\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Records paying an approved bill: clears the payable against the bank account.
 */
class PayVendorBill
{
    public function __construct(
        private readonly PostJournalEntry $postJournalEntry,
        private readonly ChartOfAccounts $chartOfAccounts,
    ) {}

    /**
     * @throws LogicException
     */
    public function handle(VendorBill $bill, PaymentMethod $method, CarbonInterface $paidOn, ?string $reference, User $paidBy): void
    {
        DB::transaction(function () use ($bill, $method, $paidOn, $reference, $paidBy): void {
            $bill = VendorBill::query()->withoutGlobalScopes()->whereKey($bill->id)->lockForUpdate()->with(['community', 'vendor'])->firstOrFail();

            if ($bill->status !== VendorBillStatus::Approved) {
                throw new LogicException(__('Only an approved, unpaid bill can be paid.'));
            }

            if ($paidOn->lessThan($bill->billed_on)) {
                throw new LogicException(__('A bill cannot be paid before its date.'));
            }

            $community = $bill->community;
            $amount = Money::of($bill->amount_cents, $community->currency);

            $entry = $this->postJournalEntry->handle(
                $community,
                $paidOn,
                __('Paid :number · :vendor (:method)', ['number' => $bill->displayNumber(), 'vendor' => $bill->vendor->name, 'method' => $method->label()]),
                [
                    JournalLine::debit($this->chartOfAccounts->account($community, SystemAccount::Payables), $amount, memo: $bill->vendor->name),
                    JournalLine::credit($this->chartOfAccounts->account($community, SystemAccount::Cash), $amount, memo: $reference),
                ],
                $bill,
                $paidBy,
            );

            $bill->forceFill([
                'status' => VendorBillStatus::Paid,
                'paid_on' => $paidOn->toDateString(),
                'payment_method' => $method,
                'payment_reference' => $reference,
                'paid_by_id' => $paidBy->id,
                'payment_journal_entry_id' => $entry->id,
            ])->save();
        });
    }
}

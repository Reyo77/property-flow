<?php

namespace App\Actions\Finance;

use App\Enums\AccountType;
use App\Enums\VendorBillStatus;
use App\Models\Account;
use App\Models\Community;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorBill;
use App\Support\Finance\DocumentNumbers;
use App\Support\Finance\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Records a vendor's bill, awaiting approval. Nothing is posted until it is approved.
 */
class SubmitVendorBill
{
    public function __construct(private readonly DocumentNumbers $documentNumbers) {}

    public function handle(
        Community $community,
        Vendor $vendor,
        Account $expenseAccount,
        Money $amount,
        CarbonInterface $billedOn,
        CarbonInterface $dueOn,
        string $description,
        ?string $vendorReference,
        User $submittedBy,
    ): VendorBill {
        if (! $amount->isPositive()) {
            throw new InvalidArgumentException('A bill must be for more than zero.');
        }

        if ($vendor->company_id !== $community->company_id) {
            throw new InvalidArgumentException('The vendor belongs to another company.');
        }

        if ($expenseAccount->community_id !== $community->id || $expenseAccount->type !== AccountType::Expense || ! $expenseAccount->is_active) {
            throw new InvalidArgumentException('Bills must be charged to an active expense account of this community.');
        }

        if ($dueOn->lessThan($billedOn->copy()->startOfDay())) {
            throw new InvalidArgumentException('A bill cannot be due before its date.');
        }

        return DB::transaction(function () use ($community, $vendor, $expenseAccount, $amount, $billedOn, $dueOn, $description, $vendorReference, $submittedBy): VendorBill {
            $bill = new VendorBill;
            $bill->forceFill([
                'company_id' => $community->company_id,
                'community_id' => $community->id,
                'vendor_id' => $vendor->id,
                'account_id' => $expenseAccount->id,
                'number' => $this->documentNumbers->next($community, VendorBill::class),
                'vendor_reference' => $vendorReference,
                'description' => $description,
                'amount_cents' => $amount->cents,
                'billed_on' => $billedOn->toDateString(),
                'due_on' => $dueOn->toDateString(),
                'status' => VendorBillStatus::Pending,
                'submitted_by_id' => $submittedBy->id,
            ])->save();

            return $bill;
        });
    }
}

<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\VendorBillStatus;
use App\Models\Concerns\BelongsToCommunity;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Contracts\BelongsToOneCommunity;
use Database\Factories\VendorBillFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A bill from a vendor. Nothing reaches the ledger until it is approved (expense and payable);
 * paying it clears the payable against cash.
 *
 * @property int $id
 * @property int $company_id
 * @property int $community_id
 * @property int $vendor_id
 * @property int $account_id
 * @property int $number
 * @property string|null $vendor_reference
 * @property string $description
 * @property int $amount_cents
 * @property Carbon $billed_on
 * @property Carbon $due_on
 * @property VendorBillStatus $status
 * @property int|null $submitted_by_id
 * @property int|null $decided_by_id
 * @property Carbon|null $decided_at
 * @property string|null $decision_notes
 * @property int|null $approval_journal_entry_id
 * @property Carbon|null $paid_on
 * @property PaymentMethod|null $payment_method
 * @property string|null $payment_reference
 * @property int|null $paid_by_id
 * @property int|null $payment_journal_entry_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Community $community
 * @property-read Vendor $vendor
 * @property-read Account $account
 * @property-read User|null $submittedBy
 * @property-read User|null $decidedBy
 */
class VendorBill extends Model implements BelongsToOneCommunity
{
    /** @use HasFactory<VendorBillFactory> */
    use BelongsToCommunity, BelongsToCompany, HasFactory, LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => VendorBillStatus::class,
            'payment_method' => PaymentMethod::class,
            'amount_cents' => 'integer',
            'number' => 'integer',
            'billed_on' => 'date',
            'due_on' => 'date',
            'paid_on' => 'date',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Community, $this>
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_id');
    }

    /**
     * Whether approving this bill is beyond a manager's limit and needs the board.
     */
    public function needsLargeBillApproval(): bool
    {
        return $this->amount_cents > $this->community->bill_approval_limit_cents;
    }

    public function displayNumber(): string
    {
        return 'BILL-'.str_pad((string) $this->number, 6, '0', STR_PAD_LEFT);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'amount_cents', 'decided_by_id', 'paid_on'])->logOnlyDirty();
    }
}

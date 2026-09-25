<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentReversalReason;
use App\Models\Concerns\BelongsToCommunity;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Contracts\BelongsToOneCommunity;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Money received from a unit. Posted to the ledger when recorded; a refund or returned (NSF)
 * payment is handled by reversing that posting, never by editing the payment.
 *
 * @property int $id
 * @property int $company_id
 * @property int $community_id
 * @property int $unit_id
 * @property int $number
 * @property PaymentMethod $method
 * @property string|null $reference
 * @property string|null $gateway_reference
 * @property int $amount_cents
 * @property Carbon $received_on
 * @property string|null $memo
 * @property int|null $journal_entry_id
 * @property int|null $recorded_by_id
 * @property Carbon|null $reversed_at
 * @property PaymentReversalReason|null $reversal_reason
 * @property int|null $reversal_journal_entry_id
 * @property int|null $reversed_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Community $community
 * @property-read Unit $unit
 * @property-read User|null $recordedBy
 */
class Payment extends Model implements BelongsToOneCommunity
{
    /** @use HasFactory<PaymentFactory> */
    use BelongsToCommunity, BelongsToCompany, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'reversal_reason' => PaymentReversalReason::class,
            'received_on' => 'date',
            'reversed_at' => 'datetime',
            'amount_cents' => 'integer',
            'number' => 'integer',
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
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return HasMany<PaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null;
    }

    public function allocatedCents(): int
    {
        return (int) $this->allocations()->sum('amount_cents');
    }

    /**
     * What's left of this payment as a credit on the unit's account, waiting for a future invoice.
     */
    public function unallocatedCents(): int
    {
        return $this->isReversed() ? 0 : $this->amount_cents - $this->allocatedCents();
    }

    public function displayNumber(): string
    {
        return 'RCT-'.str_pad((string) $this->number, 6, '0', STR_PAD_LEFT);
    }
}

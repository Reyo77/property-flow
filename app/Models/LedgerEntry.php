<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCommunity;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\IsAppendOnly;
use App\Models\Contracts\BelongsToOneCommunity;
use Database\Factories\LedgerEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One debit or credit line of a {@see JournalEntry}. Lines on the receivables account carry
 * the unit they belong to, which is what makes a unit's ledger (its resident statement).
 *
 * @property int $id
 * @property int $company_id
 * @property int $community_id
 * @property int $journal_entry_id
 * @property int $account_id
 * @property int|null $unit_id
 * @property Carbon $posted_on
 * @property int $debit_cents
 * @property int $credit_cents
 * @property string|null $memo
 * @property Carbon|null $created_at
 * @property-read JournalEntry $journalEntry
 * @property-read Account $account
 * @property-read Unit|null $unit
 */
class LedgerEntry extends Model implements BelongsToOneCommunity
{
    /** @use HasFactory<LedgerEntryFactory> */
    use BelongsToCommunity, BelongsToCompany, HasFactory, IsAppendOnly;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'posted_on' => 'date',
            'debit_cents' => 'integer',
            'credit_cents' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Debit minus credit: positive for a debit line, negative for a credit line.
     */
    public function netCents(): int
    {
        return $this->debit_cents - $this->credit_cents;
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\BankStatementLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int $bank_statement_id
 * @property Carbon $posted_on
 * @property string $description
 * @property string|null $reference
 * @property int $amount_cents Deposits positive, withdrawals negative.
 * @property int|null $ledger_entry_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BankStatement $bankStatement
 * @property-read LedgerEntry|null $ledgerEntry
 */
class BankStatementLine extends Model
{
    /** @use HasFactory<BankStatementLineFactory> */
    use BelongsToCompany, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'posted_on' => 'date',
            'amount_cents' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<BankStatement, $this>
     */
    public function bankStatement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class);
    }

    /**
     * @return BelongsTo<LedgerEntry, $this>
     */
    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class);
    }

    public function isMatched(): bool
    {
        return $this->ledger_entry_id !== null;
    }
}

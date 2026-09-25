<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCommunity;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Contracts\BelongsToOneCommunity;
use Database\Factories\BankStatementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An imported bank statement for the community's bank account, reconciled by matching its lines
 * to ledger lines on that account.
 *
 * @property int $id
 * @property int $company_id
 * @property int $community_id
 * @property int $account_id
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property int $closing_balance_cents
 * @property string|null $filename
 * @property int|null $imported_by_id
 * @property Carbon|null $reconciled_at
 * @property int|null $reconciled_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Community $community
 * @property-read Account $account
 */
class BankStatement extends Model implements BelongsToOneCommunity
{
    /** @use HasFactory<BankStatementFactory> */
    use BelongsToCommunity, BelongsToCompany, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'closing_balance_cents' => 'integer',
            'reconciled_at' => 'datetime',
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
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return HasMany<BankStatementLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class);
    }

    public function isReconciled(): bool
    {
        return $this->reconciled_at !== null;
    }
}

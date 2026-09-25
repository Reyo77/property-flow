<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCommunity;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\IsAppendOnly;
use App\Models\Contracts\BelongsToOneCommunity;
use Database\Factories\JournalEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One balanced posting to the ledger (its lines always sum to equal debits and credits).
 * Append-only: see {@see IsAppendOnly}.
 *
 * @property int $id
 * @property int $company_id
 * @property int $community_id
 * @property int $fiscal_year_id
 * @property Carbon $posted_on
 * @property string $memo
 * @property string|null $source_type
 * @property int|null $source_id
 * @property int|null $reverses_id
 * @property int|null $created_by_id
 * @property Carbon|null $created_at
 * @property-read Community $community
 * @property-read FiscalYear $fiscalYear
 * @property-read JournalEntry|null $reverses
 * @property-read JournalEntry|null $reversal
 * @property-read User|null $createdBy
 */
class JournalEntry extends Model implements BelongsToOneCommunity
{
    /** @use HasFactory<JournalEntryFactory> */
    use BelongsToCommunity, BelongsToCompany, HasFactory, IsAppendOnly;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'posted_on' => 'date',
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
     * @return BelongsTo<FiscalYear, $this>
     */
    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    /**
     * @return HasMany<LedgerEntry, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The entry this one cancels out, when this is a reversal.
     *
     * @return BelongsTo<JournalEntry, $this>
     */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reverses_id');
    }

    /**
     * The reversal that cancelled this entry, if any.
     *
     * @return HasOne<JournalEntry, $this>
     */
    public function reversal(): HasOne
    {
        return $this->hasOne(JournalEntry::class, 'reverses_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function isReversal(): bool
    {
        return $this->reverses_id !== null;
    }
}

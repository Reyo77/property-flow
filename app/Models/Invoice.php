<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\BelongsToCommunity;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Contracts\BelongsToOneCommunity;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A bill to a unit. Its amounts live on the ledger (posted when issued, reversed when voided);
 * this record adds what the ledger doesn't know: due date, line descriptions and which payments
 * have been applied to it.
 *
 * @property int $id
 * @property int $company_id
 * @property int $community_id
 * @property int $unit_id
 * @property int $number
 * @property Carbon $issued_on
 * @property Carbon $due_on
 * @property string|null $memo
 * @property int $total_cents
 * @property string|null $source_type
 * @property int|null $source_id
 * @property string|null $billing_key
 * @property int|null $journal_entry_id
 * @property Carbon|null $voided_at
 * @property Carbon|null $overdue_notified_at
 * @property int|null $void_journal_entry_id
 * @property int|null $created_by_id
 * @property int|null $paid_cents Only present when loaded with the withPaid scope.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Community $community
 * @property-read Unit $unit
 * @property-read JournalEntry|null $journalEntry
 */
class Invoice extends Model implements BelongsToOneCommunity
{
    /** @use HasFactory<InvoiceFactory> */
    use BelongsToCommunity, BelongsToCompany, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'due_on' => 'date',
            'voided_at' => 'datetime',
            'overdue_notified_at' => 'datetime',
            'total_cents' => 'integer',
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
     * @return HasMany<InvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    /**
     * @return HasMany<PaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Loads `paid_cents`: the sum of allocations from payments that haven't been reversed.
     *
     * @param  Builder<Invoice>  $query
     */
    #[Scope]
    protected function withPaid(Builder $query): void
    {
        $query->withSum(['allocations as paid_cents' => fn (Builder $allocations) => $allocations->whereHas(
            'payment',
            fn (Builder $payment) => $payment->whereNull('reversed_at'),
        )], 'amount_cents');
    }

    /**
     * Issued, not voided, and not yet fully paid — oldest due first.
     *
     * @param  Builder<Invoice>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereNull('voided_at')->orderBy('due_on')->orderBy('issued_on')->orderBy('id');
    }

    public function paidCents(): int
    {
        if (array_key_exists('paid_cents', $this->attributes)) {
            return (int) $this->attributes['paid_cents'];
        }

        return (int) $this->allocations()
            ->whereHas('payment', fn (Builder $payment) => $payment->whereNull('reversed_at'))
            ->sum('amount_cents');
    }

    public function balanceCents(): int
    {
        return $this->isVoided() ? 0 : $this->total_cents - $this->paidCents();
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function isOverdue(): bool
    {
        return $this->balanceCents() > 0 && $this->due_on->isPast() && ! $this->due_on->isToday();
    }

    public function status(): InvoiceStatus
    {
        if ($this->isVoided()) {
            return InvoiceStatus::Voided;
        }

        $paid = $this->paidCents();

        return match (true) {
            $paid >= $this->total_cents => InvoiceStatus::Paid,
            $paid > 0 => InvoiceStatus::PartiallyPaid,
            default => InvoiceStatus::Open,
        };
    }

    public function displayNumber(): string
    {
        return 'INV-'.str_pad((string) $this->number, 6, '0', STR_PAD_LEFT);
    }
}

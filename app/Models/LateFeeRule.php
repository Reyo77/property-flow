<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCommunity;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Contracts\BelongsToOneCommunity;
use App\Support\Finance\Money;
use Database\Factories\LateFeeRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A community's late fee policy: once an invoice is more than `grace_days` past due with at least
 * `minimum_balance_cents` still owing, it is charged one late fee — flat, or a percentage of
 * what is still owing.
 *
 * @property int $id
 * @property int $company_id
 * @property int $community_id
 * @property int $grace_days
 * @property int|null $flat_cents
 * @property int|null $percent_basis_points
 * @property int $minimum_balance_cents
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Community $community
 */
#[Fillable(['grace_days', 'flat_cents', 'percent_basis_points', 'minimum_balance_cents', 'is_active'])]
class LateFeeRule extends Model implements BelongsToOneCommunity
{
    /** @use HasFactory<LateFeeRuleFactory> */
    use BelongsToCommunity, BelongsToCompany, HasFactory, LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grace_days' => 'integer',
            'flat_cents' => 'integer',
            'percent_basis_points' => 'integer',
            'minimum_balance_cents' => 'integer',
            'is_active' => 'boolean',
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
     * The fee for an invoice with this much still owing (zero when it's below the minimum).
     */
    public function feeFor(Money $balance): Money
    {
        if ($balance->cents < max(1, $this->minimum_balance_cents)) {
            return Money::zero($balance->currency);
        }

        if ($this->flat_cents !== null) {
            return Money::of($this->flat_cents, $balance->currency);
        }

        return $balance->percentOf((int) $this->percent_basis_points);
    }

    public function describe(): string
    {
        $fee = $this->flat_cents !== null
            ? Money::of($this->flat_cents)->format()
            : rtrim(rtrim(number_format((int) $this->percent_basis_points / 100, 2), '0'), '.').'%';

        return __(':fee once an invoice is :days days overdue', ['fee' => $fee, 'days' => $this->grace_days]);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}

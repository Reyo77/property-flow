<?php

namespace App\Models;

use App\Enums\RecurringChargeMethod;
use App\Models\Concerns\BelongsToCommunity;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Contracts\BelongsToOneCommunity;
use Carbon\CarbonInterface;
use Database\Factories\RecurringChargeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A charge the monthly billing run puts on units' invoices: to every unit (a fixed amount each,
 * or a total split by unit factor) or to one unit (a parking spot, a locker).
 *
 * @property int $id
 * @property int $company_id
 * @property int $community_id
 * @property int $charge_type_id
 * @property int|null $unit_id
 * @property string $description
 * @property RecurringChargeMethod $method
 * @property int $amount_cents
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Community $community
 * @property-read ChargeType $chargeType
 * @property-read Unit|null $unit
 */
#[Fillable(['charge_type_id', 'unit_id', 'description', 'method', 'amount_cents', 'starts_on', 'ends_on', 'is_active'])]
class RecurringCharge extends Model implements BelongsToOneCommunity
{
    /** @use HasFactory<RecurringChargeFactory> */
    use BelongsToCommunity, BelongsToCompany, HasFactory, LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => RecurringChargeMethod::class,
            'amount_cents' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
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
     * @return BelongsTo<ChargeType, $this>
     */
    public function chargeType(): BelongsTo
    {
        return $this->belongsTo(ChargeType::class);
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Active charges that apply to at least part of the month starting on $month.
     *
     * @param  Builder<RecurringCharge>  $query
     */
    #[Scope]
    protected function billableIn(Builder $query, CarbonInterface $month): void
    {
        $query->where('is_active', true)
            ->whereDate('starts_on', '<=', $month->copy()->endOfMonth()->toDateString())
            ->where(fn (Builder $query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $month->copy()->startOfMonth()->toDateString()));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}

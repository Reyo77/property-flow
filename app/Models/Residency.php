<?php

namespace App\Models;

use App\Enums\ResidencyType;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\ResidencyFactory;
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
 * A resident's link to a unit, as owner, tenant or occupant, for a period of time.
 *
 * @property int $id
 * @property int $community_id
 * @property int $unit_id
 * @property int $resident_id
 * @property ResidencyType $type
 * @property bool $is_primary
 * @property Carbon|null $moved_in_on
 * @property Carbon|null $moved_out_on
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Community $community
 * @property-read Unit $unit
 * @property-read Resident $resident
 */
#[Fillable(['resident_id', 'type', 'is_primary', 'moved_in_on', 'moved_out_on'])]
class Residency extends Model
{
    /** @use HasFactory<ResidencyFactory> */
    use BelongsToCompany, HasFactory, LogsActivity;

    public static function booted(): void
    {
        static::creating(function (Residency $residency): void {
            $residency->community_id ??= $residency->unit->community_id;
            $residency->company_id ??= $residency->unit->company_id;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ResidencyType::class,
            'is_primary' => 'boolean',
            'moved_in_on' => 'date',
            'moved_out_on' => 'date',
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
     * @return BelongsTo<Resident, $this>
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    /**
     * Residencies that have not ended: no move-out date, or one in the future.
     *
     * @param  Builder<Residency>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->whereNull($query->qualifyColumn('moved_out_on'))
            ->orWhere($query->qualifyColumn('moved_out_on'), '>', today()));
    }

    /**
     * @param  Builder<Residency>  $query
     */
    #[Scope]
    protected function past(Builder $query): void
    {
        $query->where($query->qualifyColumn('moved_out_on'), '<=', today());
    }

    public function isActive(): bool
    {
        return $this->moved_out_on === null || $this->moved_out_on->isFuture();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}

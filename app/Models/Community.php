<?php

namespace App\Models;

use App\Enums\AreaUnit;
use App\Enums\CommunityType;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\CommunityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $name
 * @property CommunityType $type
 * @property string|null $address_line_1
 * @property string|null $address_line_2
 * @property string|null $city
 * @property string|null $region
 * @property string|null $postal_code
 * @property string $country
 * @property string $timezone
 * @property string $currency
 * @property AreaUnit $area_unit
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'name', 'type', 'address_line_1', 'address_line_2', 'city', 'region',
    'postal_code', 'country', 'timezone', 'currency', 'area_unit',
])]
class Community extends Model
{
    /** @use HasFactory<CommunityFactory> */
    use BelongsToCompany, HasFactory, LogsActivity, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CommunityType::class,
            'area_unit' => AreaUnit::class,
        ];
    }

    /**
     * @return HasMany<Building, $this>
     */
    public function buildings(): HasMany
    {
        return $this->hasMany(Building::class);
    }

    /**
     * @return HasMany<Unit, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    /**
     * The sum of all unit factors as an exact decimal string, or null when no unit has one.
     *
     * @return numeric-string|null
     */
    public function totalUnitFactor(): ?string
    {
        $total = $this->units()->toBase()->selectRaw('SUM(unit_factor) as total')->value('total');

        return is_string($total) && is_numeric($total) ? $total : null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}

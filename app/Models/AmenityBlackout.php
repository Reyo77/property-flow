<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\AmenityBlackoutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A date range an amenity is entirely unavailable for booking (closed for maintenance, a
 * private event, etc.).
 *
 * @property int $id
 * @property int $company_id
 * @property int $amenity_id
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property string|null $reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Amenity $amenity
 */
#[Fillable(['starts_on', 'ends_on', 'reason'])]
class AmenityBlackout extends Model
{
    /** @use HasFactory<AmenityBlackoutFactory> */
    use BelongsToCompany, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Amenity, $this>
     */
    public function amenity(): BelongsTo
    {
        return $this->belongsTo(Amenity::class);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $resident_id
 * @property string $plate
 * @property string|null $make
 * @property string|null $model
 * @property string|null $colour
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Resident $resident
 */
#[Fillable(['plate', 'make', 'model', 'colour'])]
class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use BelongsToCompany, HasFactory, LogsActivity;

    /**
     * @return BelongsTo<Resident, $this>
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}

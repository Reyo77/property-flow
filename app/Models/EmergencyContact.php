<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\EmergencyContactFactory;
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
 * @property string $name
 * @property string|null $relationship
 * @property string $phone
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Resident $resident
 */
#[Fillable(['name', 'relationship', 'phone'])]
class EmergencyContact extends Model
{
    /** @use HasFactory<EmergencyContactFactory> */
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

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCommunity;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Contracts\BelongsToOneCommunity;
use Database\Factories\VisitorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A staff-logged visitor entry (contractors, guests announced at the desk, etc.).
 *
 * @property int $id
 * @property int $community_id
 * @property int|null $unit_id
 * @property string $visitor_name
 * @property string|null $purpose
 * @property Carbon $checked_in_at
 * @property Carbon|null $checked_out_at
 * @property int|null $logged_by_id
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Community $community
 * @property-read Unit|null $unit
 * @property-read User|null $loggedBy
 */
#[Fillable(['unit_id', 'visitor_name', 'purpose', 'checked_in_at', 'checked_out_at', 'notes'])]
class Visitor extends Model implements BelongsToOneCommunity
{
    /** @use HasFactory<VisitorFactory> */
    use BelongsToCommunity, BelongsToCompany, HasFactory, LogsActivity;

    protected static function booted(): void
    {
        static::creating(function (Visitor $visitor): void {
            if ($visitor->getAttribute('checked_in_at') === null) {
                $visitor->forceFill(['checked_in_at' => now()]);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function loggedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}

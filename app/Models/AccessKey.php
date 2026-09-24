<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCommunity;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Contracts\BelongsToOneCommunity;
use Database\Factories\AccessKeyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A physical key or fob tracked through sign-out/return (a unit master, a pool gate fob, etc.).
 *
 * @property int $id
 * @property int $community_id
 * @property int|null $unit_id
 * @property string $label
 * @property string|null $notes
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Community $community
 * @property-read Unit|null $unit
 */
#[Fillable(['unit_id', 'label', 'notes', 'active'])]
class AccessKey extends Model implements BelongsToOneCommunity
{
    /** @use HasFactory<AccessKeyFactory> */
    use BelongsToCommunity, BelongsToCompany, HasFactory, LogsActivity, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
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
     * @return HasMany<AccessKeySignout, $this>
     */
    public function signouts(): HasMany
    {
        return $this->hasMany(AccessKeySignout::class);
    }

    public function currentSignout(): ?AccessKeySignout
    {
        return $this->signouts()->whereNull('returned_at')->latest('signed_out_at')->first();
    }

    public function isSignedOut(): bool
    {
        return $this->currentSignout() !== null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}

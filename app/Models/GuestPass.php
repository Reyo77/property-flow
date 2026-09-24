<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCommunity;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Contracts\BelongsToOneCommunity;
use Database\Factories\GuestPassFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A resident-created pass for an expected guest, redeemed at the front desk with its code.
 *
 * @property int $id
 * @property int $community_id
 * @property int $unit_id
 * @property int $resident_id
 * @property string $guest_name
 * @property Carbon $valid_from
 * @property Carbon $valid_until
 * @property string $code
 * @property Carbon|null $used_at
 * @property int|null $used_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Community $community
 * @property-read Unit $unit
 * @property-read Resident $resident
 * @property-read User|null $usedBy
 */
#[Fillable(['unit_id', 'resident_id', 'guest_name', 'valid_from', 'valid_until'])]
class GuestPass extends Model implements BelongsToOneCommunity
{
    /** @use HasFactory<GuestPassFactory> */
    use BelongsToCommunity, BelongsToCompany, HasFactory, LogsActivity;

    protected static function booted(): void
    {
        static::creating(function (GuestPass $guestPass): void {
            $guestPass->code ??= self::generateUniqueCode();
        });
    }

    public static function generateUniqueCode(): string
    {
        do {
            $code = Str::upper(Str::random(6));
        } while (self::withoutGlobalScopes()->where('code', $code)->exists());

        return $code;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_until' => 'date',
            'used_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function usedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by_id');
    }

    public function isValidToday(): bool
    {
        $today = now()->toDateString();

        return $this->used_at === null && $this->valid_from->toDateString() <= $today && $this->valid_until->toDateString() >= $today;
    }

    /**
     * @param  Builder<GuestPass>  $query
     */
    #[Scope]
    protected function unused(Builder $query): void
    {
        $query->whereNull('used_at');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}

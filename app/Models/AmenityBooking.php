<?php

namespace App\Models;

use App\Enums\AmenityBookingStatus;
use App\Models\Concerns\BelongsToCommunity;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Contracts\BelongsToOneCommunity;
use Database\Factories\AmenityBookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use LogicException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $community_id
 * @property int $amenity_id
 * @property int|null $unit_id
 * @property int|null $resident_id
 * @property int $booked_by_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property AmenityBookingStatus $status
 * @property int|null $fee_cents
 * @property int|null $deposit_cents
 * @property Carbon|null $terms_accepted_at
 * @property string|null $notes
 * @property int|null $decided_by_id
 * @property Carbon|null $decided_at
 * @property string|null $decision_notes
 * @property int|null $cancelled_by_id
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Community $community
 * @property-read Amenity $amenity
 * @property-read Unit|null $unit
 * @property-read Resident|null $resident
 * @property-read User $bookedBy
 * @property-read User|null $decidedBy
 * @property-read User|null $cancelledBy
 */
#[Fillable(['unit_id', 'resident_id', 'starts_at', 'ends_at', 'fee_cents', 'deposit_cents', 'terms_accepted_at', 'notes'])]
class AmenityBooking extends Model implements BelongsToOneCommunity
{
    /** @use HasFactory<AmenityBookingFactory> */
    use BelongsToCommunity, BelongsToCompany, HasFactory, LogsActivity, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (AmenityBooking $booking): void {
            $booking->status ??= AmenityBookingStatus::Pending;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => AmenityBookingStatus::class,
            'terms_accepted_at' => 'datetime',
            'decided_at' => 'datetime',
            'cancelled_at' => 'datetime',
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
     * @return BelongsTo<Amenity, $this>
     */
    public function amenity(): BelongsTo
    {
        return $this->belongsTo(Amenity::class);
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
    public function bookedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_id');
    }

    public function isOwnedBy(User $user): bool
    {
        return $user->resident !== null && $this->resident_id === $user->resident->id;
    }

    /**
     * Move the booking to a new status, refusing any transition the state machine forbids.
     *
     * @throws LogicException
     */
    public function transitionTo(AmenityBookingStatus $target): void
    {
        if (! $this->status->canTransitionTo($target)) {
            throw new LogicException("Cannot move an amenity booking from {$this->status->value} to {$target->value}.");
        }

        $this->forceFill(['status' => $target])->save();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status'])->logOnlyDirty();
    }
}

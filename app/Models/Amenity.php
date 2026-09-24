<?php

namespace App\Models;

use App\Actions\Amenities\CreateAmenityBooking;
use App\Models\Concerns\BelongsToCommunity;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Contracts\BelongsToOneCommunity;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\AmenityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use RuntimeException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A bookable shared resource (party room, pool, gym, guest suite...). Hours and slot length
 * define a fixed daily grid in the community's own timezone; {@see availableSlots()} walks
 * that grid for one local calendar date and applies every booking rule except capacity locking,
 * which {@see CreateAmenityBooking} re-checks under a row lock.
 *
 * @property int $id
 * @property int $company_id
 * @property int $community_id
 * @property int|null $created_by_id
 * @property string $name
 * @property string|null $description
 * @property string|null $location
 * @property int $opens_at_minutes
 * @property int $closes_at_minutes
 * @property list<int>|null $closed_weekdays
 * @property int $slot_minutes
 * @property int $capacity
 * @property int|null $max_bookings_per_unit
 * @property int|null $max_bookings_period_days
 * @property int|null $advance_booking_days
 * @property int|null $min_notice_hours
 * @property int|null $cancellation_notice_hours
 * @property bool $needs_approval
 * @property int|null $fee_cents
 * @property int|null $deposit_cents
 * @property string|null $terms
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Community $community
 * @property-read User|null $createdBy
 */
#[Fillable([
    'name', 'description', 'location', 'opens_at_minutes', 'closes_at_minutes', 'closed_weekdays',
    'slot_minutes', 'capacity', 'max_bookings_per_unit', 'max_bookings_period_days',
    'advance_booking_days', 'min_notice_hours', 'cancellation_notice_hours', 'needs_approval',
    'fee_cents', 'deposit_cents', 'terms', 'active',
])]
class Amenity extends Model implements BelongsToOneCommunity
{
    /** @use HasFactory<AmenityFactory> */
    use BelongsToCommunity, BelongsToCompany, HasFactory, LogsActivity, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'closed_weekdays' => 'array',
            'needs_approval' => 'boolean',
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
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * @return HasMany<AmenityBlackout, $this>
     */
    public function blackouts(): HasMany
    {
        return $this->hasMany(AmenityBlackout::class);
    }

    /**
     * @return HasMany<AmenityBooking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(AmenityBooking::class);
    }

    /**
     * @param  Builder<Amenity>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('active', true);
    }

    public function isClosedOn(CarbonInterface $localDate): bool
    {
        if (in_array($localDate->dayOfWeek, $this->closed_weekdays ?? [], true)) {
            return true;
        }

        return $this->blackouts()
            ->whereDate('starts_on', '<=', $localDate->toDateString())
            ->whereDate('ends_on', '>=', $localDate->toDateString())
            ->exists();
    }

    /**
     * The earliest local calendar date a booking may start on (today, in the community's timezone).
     */
    public function minBookableDate(): CarbonImmutable
    {
        return CarbonImmutable::now($this->community->timezone)->startOfDay();
    }

    /**
     * The latest local calendar date a booking may start on, or null when there is no limit.
     */
    public function maxBookableDate(): ?CarbonImmutable
    {
        return $this->advance_booking_days === null
            ? null
            : $this->minBookableDate()->addDays($this->advance_booking_days);
    }

    /**
     * Every slot on the fixed daily grid for one local calendar date, with remaining capacity.
     * Slots that fail a timing rule (too soon, before today, or beyond the advance window) are
     * left out entirely; slots at capacity are still returned, marked not bookable, so the UI
     * can show them as full rather than making them disappear.
     *
     * @return list<array{starts_at: CarbonImmutable, ends_at: CarbonImmutable, remaining: int, bookable: bool}>
     */
    public function availableSlots(CarbonInterface $localDate): array
    {
        $localDate = $this->localInstant($localDate, 0);

        if (! $this->active || $this->isClosedOn($localDate) || $localDate->lt($this->minBookableDate())) {
            return [];
        }

        if (($maxDate = $this->maxBookableDate()) !== null && $localDate->gt($maxDate)) {
            return [];
        }

        $dayStart = $localDate->utc();
        $dayEnd = $this->localInstant($localDate, (24 * 60) - 1)->utc();

        $bookedCounts = $this->bookings()
            ->whereBetween('starts_at', [$dayStart, $dayEnd])
            ->get(['starts_at', 'status'])
            ->filter(fn (AmenityBooking $booking) => $booking->status->blocksCapacity())
            ->countBy(fn (AmenityBooking $booking) => $booking->starts_at->toIso8601String());

        // A slot that has already started is never offered, even without an explicit notice window.
        $earliestBookable = $this->min_notice_hours === null
            ? CarbonImmutable::now()
            : CarbonImmutable::now()->addHours($this->min_notice_hours);

        $slots = [];

        for ($minute = $this->opens_at_minutes; $minute + $this->slot_minutes <= $this->closes_at_minutes; $minute += $this->slot_minutes) {
            $startsAt = $this->localInstant($localDate, $minute)->utc();

            if ($startsAt->lt($earliestBookable)) {
                continue;
            }

            $remaining = max(0, $this->capacity - ($bookedCounts[$startsAt->toIso8601String()] ?? 0));

            $slots[] = [
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->addMinutes($this->slot_minutes),
                'remaining' => $remaining,
                'bookable' => $remaining > 0,
            ];
        }

        return $slots;
    }

    /**
     * Re-validates a specific instant against every timing rule and the daily grid, so a
     * booking request can't be crafted for a time the UI never offered.
     */
    public function isBookable(CarbonInterface $startsAtUtc): bool
    {
        // Defensive clone: $startsAtUtc may be a mutable Carbon instance from the caller, and
        // setTimezone() must not silently change what timezone their own object displays in.
        $localDate = $startsAtUtc->clone()->setTimezone($this->community->timezone);

        foreach ($this->availableSlots($localDate) as $slot) {
            if ($slot['starts_at']->equalTo($startsAtUtc)) {
                return $slot['bookable'];
            }
        }

        return false;
    }

    /**
     * Builds the UTC-independent local instant for a calendar date plus a number of minutes
     * since local midnight, resolving that date's own DST offset (not a fixed one carried over
     * from another day).
     */
    private function localInstant(CarbonInterface $localDate, int $minutesFromMidnight): CarbonImmutable
    {
        $instant = CarbonImmutable::create(
            $localDate->year,
            $localDate->month,
            $localDate->day,
            intdiv($minutesFromMidnight, 60),
            $minutesFromMidnight % 60,
            0,
            $this->community->timezone,
        );

        if ($instant === null) {
            throw new RuntimeException('Invalid amenity slot time.');
        }

        return $instant;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}

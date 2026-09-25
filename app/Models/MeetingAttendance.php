<?php

namespace App\Models;

use App\Enums\AttendanceMode;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\MeetingAttendanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A unit being represented at a meeting — by an owner in person or online, or by a proxy.
 *
 * @property int $id
 * @property int $company_id
 * @property int $meeting_id
 * @property int $unit_id
 * @property AttendanceMode $represented_by
 * @property string|null $attendee_name
 * @property int|null $recorded_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Meeting $meeting
 * @property-read Unit $unit
 */
class MeetingAttendance extends Model
{
    /** @use HasFactory<MeetingAttendanceFactory> */
    use BelongsToCompany, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['represented_by' => AttendanceMode::class];
    }

    /**
     * @return BelongsTo<Meeting, $this>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class)->withTrashed();
    }
}

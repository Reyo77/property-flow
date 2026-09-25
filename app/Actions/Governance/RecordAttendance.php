<?php

namespace App\Actions\Governance;

use App\Enums\AttendanceMode;
use App\Models\Meeting;
use App\Models\MeetingAttendance;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Checks a unit in at a meeting. Recording it again just updates how it's represented.
 */
class RecordAttendance
{
    /**
     * @throws ValidationException
     */
    public function handle(Meeting $meeting, Unit $unit, AttendanceMode $mode, ?string $attendeeName, User $recordedBy): MeetingAttendance
    {
        if ($meeting->isClosed()) {
            throw ValidationException::withMessages(['unit_id' => __('This meeting is closed.')]);
        }

        if ($unit->community_id !== $meeting->community_id) {
            throw ValidationException::withMessages(['unit_id' => __('That unit is not in this community.')]);
        }

        $attendance = MeetingAttendance::query()->withoutGlobalScopes()
            ->where('meeting_id', $meeting->id)
            ->where('unit_id', $unit->id)
            ->first() ?? new MeetingAttendance;

        $attendance->forceFill([
            'company_id' => $meeting->company_id,
            'meeting_id' => $meeting->id,
            'unit_id' => $unit->id,
            'represented_by' => $mode,
            'attendee_name' => $attendeeName,
            'recorded_by_id' => $recordedBy->id,
        ])->save();

        return $attendance;
    }
}

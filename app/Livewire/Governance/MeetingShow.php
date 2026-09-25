<?php

namespace App\Livewire\Governance;

use App\Actions\Governance\CloseMeeting;
use App\Actions\Governance\PublishMinutes;
use App\Actions\Governance\RecordAttendance;
use App\Concerns\GovernanceValidationRules;
use App\Enums\AttendanceMode;
use App\Livewire\Concerns\InteractsWithCurrentUser;
use App\Models\Ballot;
use App\Models\Community;
use App\Models\Meeting;
use App\Models\MeetingAttendance;
use App\Models\Unit;
use App\Support\Governance\MeetingQuorum;
use App\Support\Governance\VotingRoll;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use LogicException;

#[Title('Meeting')]
class MeetingShow extends Component
{
    use GovernanceValidationRules, InteractsWithCurrentUser;

    public Community $community;

    public Meeting $meeting;

    public string $attendance_unit_id = '';

    public string $attendance_mode = '';

    public string $attendee_name = '';

    public string $minutes = '';

    public function mount(): void
    {
        $this->authorize('view', $this->meeting);

        $this->attendance_mode = AttendanceMode::InPerson->value;

        // Public properties are serialised into the page, so draft minutes are only loaded into
        // the editor for people allowed to edit them.
        if ($this->canManage()) {
            $this->minutes = (string) $this->meeting->minutes;
        }
    }

    #[Computed]
    public function canManage(): bool
    {
        return $this->currentUser()->can('manage', $this->meeting);
    }

    /**
     * @return array{eligible_units: int, represented_units: int, eligible_weight: string, represented_weight: string, percent: string, required_percent: int, met: bool}
     */
    #[Computed]
    public function quorum(): array
    {
        return app(MeetingQuorum::class)->for($this->meeting);
    }

    /**
     * @return Collection<int, MeetingAttendance>
     */
    #[Computed]
    public function attendances(): Collection
    {
        return $this->meeting->attendances()->with('unit.building')->get()->sortBy(fn (MeetingAttendance $attendance) => $attendance->unit->label(), SORT_NATURAL)->values();
    }

    /**
     * Owner units not yet checked in, for the check-in picker.
     *
     * @return \Illuminate\Support\Collection<int, Unit>
     */
    #[Computed]
    public function unitsToCheckIn(): \Illuminate\Support\Collection
    {
        $present = $this->meeting->attendances()->pluck('unit_id');

        return app(VotingRoll::class)->eligibleUnits($this->community)->except($present->all())->sortBy(fn (Unit $unit) => $unit->label(), SORT_NATURAL)->values();
    }

    /**
     * @return Collection<int, Ballot>
     */
    #[Computed]
    public function ballots(): Collection
    {
        return $this->meeting->ballots()->get()->filter(fn (Ballot $ballot) => $this->currentUser()->can('view', $ballot))->values();
    }

    public function checkIn(RecordAttendance $recordAttendance): void
    {
        $this->authorize('manage', $this->meeting);

        $validated = $this->validate($this->attendanceRules($this->community));

        try {
            $recordAttendance->handle(
                $this->meeting,
                $this->community->units()->findOrFail((int) $validated['attendance_unit_id']),
                AttendanceMode::from($validated['attendance_mode']),
                $validated['attendee_name'] ?: null,
                $this->currentUser(),
            );
        } catch (ValidationException $exception) {
            $this->addError('attendance_unit_id', $exception->validator->errors()->first());

            return;
        }

        $this->reset('attendance_unit_id', 'attendee_name');
        unset($this->quorum, $this->attendances, $this->unitsToCheckIn);
    }

    public function removeAttendance(int $attendanceId): void
    {
        $this->authorize('manage', $this->meeting);

        if ($this->meeting->isClosed()) {
            return;
        }

        $this->meeting->attendances()->findOrFail($attendanceId)->delete();
        unset($this->quorum, $this->attendances, $this->unitsToCheckIn);
    }

    public function saveMinutes(PublishMinutes $publishMinutes, bool $publish = false): void
    {
        $this->authorize('manage', $this->meeting);

        $this->validate(['minutes' => ['nullable', 'string', 'max:100000']]);

        try {
            $publishMinutes->handle($this->meeting, $this->minutes, $publish, $this->currentUser());
        } catch (LogicException $exception) {
            $this->addError('minutes', $exception->getMessage());

            return;
        }

        $this->meeting->refresh();
        Flux::toast(variant: 'success', text: $publish ? __('Minutes published to owners.') : __('Minutes saved.'));
    }

    public function close(CloseMeeting $closeMeeting): void
    {
        $this->authorize('manage', $this->meeting);

        try {
            $closeMeeting->handle($this->meeting, $this->currentUser());
        } catch (LogicException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $this->meeting->refresh();
        Flux::toast(variant: 'success', text: __('Meeting closed. Attendance is now final.'));
    }

    public function render(): View
    {
        return view('livewire.governance.meeting-show');
    }
}

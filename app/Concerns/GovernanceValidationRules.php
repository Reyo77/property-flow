<?php

namespace App\Concerns;

use App\Enums\AttendanceMode;
use App\Enums\MeetingKind;
use App\Enums\VotingWeighting;
use App\Models\Community;
use App\Models\Meeting;
use App\Models\Unit;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait GovernanceValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|Closure|array<mixed>|string>>
     */
    protected function ballotRules(Community $community): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'meeting_id' => ['nullable', 'integer', Rule::exists(Meeting::class, 'id')->where('community_id', $community->id)->whereIn('kind', [MeetingKind::Agm->value, MeetingKind::Special->value])],
            'weighting' => ['required', Rule::enum(VotingWeighting::class)],
            'quorum_percent' => ['required', 'integer', 'between:0,100'],
            'opens_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'closes_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:opens_at'],
            'questions' => ['required', 'array', 'min:1', 'max:20'],
            'questions.*.title' => ['required', 'string', 'max:500'],
            'questions.*.options' => ['required', 'array', 'min:2', 'max:10', function (string $attribute, mixed $options, Closure $fail): void {
                $labels = array_map(fn ($label) => mb_strtolower(trim(is_string($label) ? $label : '')), is_array($options) ? $options : []);

                if (count($labels) !== count(array_unique($labels))) {
                    $fail(__('Each answer to a question must be different.'));
                }
            }],
            'questions.*.options.*' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function meetingRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'kind' => ['required', Rule::enum(MeetingKind::class)],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'weighting' => ['required', Rule::enum(VotingWeighting::class)],
            'quorum_percent' => ['required', 'integer', 'between:0,100'],
            'agenda' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function attendanceRules(Community $community): array
    {
        return [
            'attendance_unit_id' => ['required', 'integer', Rule::exists(Unit::class, 'id')->where('community_id', $community->id)->withoutTrashed()],
            'attendance_mode' => ['required', Rule::enum(AttendanceMode::class)],
            'attendee_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}

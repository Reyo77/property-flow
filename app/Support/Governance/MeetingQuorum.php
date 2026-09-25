<?php

namespace App\Support\Governance;

use App\Models\Meeting;
use App\Models\MeetingAttendance;

/**
 * Whether enough of the owners are represented at a meeting (in person, online or by proxy)
 * for it to do business. Only units on the voting roll count.
 */
class MeetingQuorum
{
    public function __construct(private readonly VotingRoll $votingRoll) {}

    /**
     * @return array{eligible_units: int, represented_units: int, eligible_weight: string, represented_weight: string, percent: string, required_percent: int, met: bool}
     */
    public function for(Meeting $meeting): array
    {
        $eligible = $this->votingRoll->eligibleUnits($meeting->loadMissing('community')->community);
        $representedIds = MeetingAttendance::query()->withoutGlobalScopes()->where('meeting_id', $meeting->id)->pluck('unit_id');
        $represented = $eligible->only($representedIds->all());

        $eligibleWeight = $this->votingRoll->totalWeight($eligible, $meeting->weighting);
        $representedWeight = $this->votingRoll->totalWeight($represented, $meeting->weighting);
        $percent = VotingRoll::percent($representedWeight, $eligibleWeight);

        return [
            'eligible_units' => $eligible->count(),
            'represented_units' => $represented->count(),
            'eligible_weight' => $eligibleWeight,
            'represented_weight' => $representedWeight,
            'percent' => $percent,
            'required_percent' => $meeting->quorum_percent,
            'met' => $eligible->isNotEmpty() && bccomp($percent, (string) $meeting->quorum_percent, 2) >= 0,
        ];
    }
}

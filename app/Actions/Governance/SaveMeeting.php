<?php

namespace App\Actions\Governance;

use App\Models\Community;
use App\Models\Meeting;
use App\Models\MeetingAgendaItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class SaveMeeting
{
    /**
     * @param  array{title: string, kind: string, starts_at: string, location: string|null, description: string|null, weighting: string, quorum_percent: int}  $attributes
     * @param  list<string>  $agenda  agenda item titles, in order
     *
     * @throws LogicException for a closed meeting
     */
    public function handle(Community $community, ?Meeting $meeting, array $attributes, array $agenda, User $author): Meeting
    {
        if ($meeting?->isClosed()) {
            throw new LogicException(__('A closed meeting can no longer be changed.'));
        }

        return DB::transaction(function () use ($community, $meeting, $attributes, $agenda, $author): Meeting {
            if ($meeting === null) {
                $meeting = new Meeting($attributes);
                $meeting->forceFill([
                    'company_id' => $community->company_id,
                    'community_id' => $community->id,
                    'created_by_id' => $author->id,
                ])->save();
            } else {
                $meeting->update($attributes);
                MeetingAgendaItem::query()->where('meeting_id', $meeting->id)->delete();
            }

            foreach ($agenda as $index => $title) {
                $item = new MeetingAgendaItem(['position' => $index + 1, 'title' => $title]);
                $item->forceFill(['company_id' => $community->company_id, 'meeting_id' => $meeting->id])->save();
            }

            return $meeting;
        });
    }
}

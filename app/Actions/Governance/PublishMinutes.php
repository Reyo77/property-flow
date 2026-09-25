<?php

namespace App\Actions\Governance;

use App\Models\Meeting;
use App\Models\User;
use LogicException;

class PublishMinutes
{
    /**
     * @throws LogicException when there are no minutes yet
     */
    public function handle(Meeting $meeting, string $minutes, bool $publish, User $author): void
    {
        if ($publish && trim($minutes) === '') {
            throw new LogicException(__('Write the minutes before publishing them.'));
        }

        $meeting->forceFill([
            'minutes' => $minutes,
            'minutes_published_at' => $publish ? ($meeting->minutes_published_at ?? now()) : null,
        ])->save();

        activity()->performedOn($meeting)->causedBy($author)->log($publish ? 'minutes published' : 'minutes saved');
    }
}

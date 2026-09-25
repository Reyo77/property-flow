<?php

namespace App\Actions\Governance;

use App\Models\Meeting;
use App\Models\User;
use LogicException;

/**
 * Closes a meeting, optionally publishing its minutes to residents. Attendance and the
 * agenda are frozen from then on; minutes can still be corrected and (re)published.
 */
class CloseMeeting
{
    /**
     * @throws LogicException
     */
    public function handle(Meeting $meeting, User $closedBy): void
    {
        if ($meeting->isClosed()) {
            throw new LogicException(__('This meeting is already closed.'));
        }

        $meeting->forceFill(['closed_at' => now()])->save();

        activity()->performedOn($meeting)->causedBy($closedBy)->log('closed');
    }
}

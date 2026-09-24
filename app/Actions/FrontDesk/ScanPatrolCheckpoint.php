<?php

namespace App\Actions\FrontDesk;

use App\Events\FrontDeskActivity;
use App\Models\PatrolCheckpoint;
use App\Models\PatrolScan;
use App\Models\User;

class ScanPatrolCheckpoint
{
    public function handle(PatrolCheckpoint $checkpoint, User $scannedBy): PatrolScan
    {
        $scan = $checkpoint->scans()->make();
        $scan->forceFill(['company_id' => $checkpoint->company_id, 'scanned_by_id' => $scannedBy->id])->save();

        $checkpoint->loadMissing('patrolRoute');
        FrontDeskActivity::dispatch(
            $checkpoint->patrolRoute->community_id,
            'patrol_scan',
            __(':checkpoint scanned on :route.', ['checkpoint' => $checkpoint->name, 'route' => $checkpoint->patrolRoute->name]),
        );

        return $scan;
    }
}

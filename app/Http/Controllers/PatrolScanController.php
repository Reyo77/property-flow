<?php

namespace App\Http\Controllers;

use App\Actions\FrontDesk\ScanPatrolCheckpoint;
use App\Models\PatrolCheckpoint;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Where a checkpoint's QR code points: the guard's phone camera opens this URL (in their own
 * logged-in browser session), which records the scan against their account and confirms it.
 */
class PatrolScanController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(string $qrToken, ScanPatrolCheckpoint $scanPatrolCheckpoint): View
    {
        $checkpoint = PatrolCheckpoint::query()->where('qr_token', $qrToken)->with('patrolRoute.community')->firstOrFail();

        $this->authorize('scan', $checkpoint->patrolRoute);

        /** @var User $user */
        $user = auth()->user();

        $scan = $scanPatrolCheckpoint->handle($checkpoint, $user);

        return view('patrol-scan-confirmed', ['checkpoint' => $checkpoint, 'scan' => $scan]);
    }
}

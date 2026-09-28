<?php

namespace App\Actions\Residents;

use App\Actions\Team\SignOutEverywhere;
use App\Enums\DataDeletionStatus;
use App\Models\Resident;
use App\Models\ResidentDataDeletionRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Approving anonymizes the resident's record immediately, rather than deleting it outright, so the
 * community's history (past residencies, invoices, votes) stays intact but no longer names them.
 */
class DecideDataDeletionRequest
{
    public function handle(ResidentDataDeletionRequest $request, User $reviewedBy, bool $approve, ?string $decisionNotes): ResidentDataDeletionRequest
    {
        if (! $request->status->isOpen()) {
            throw ValidationException::withMessages(['status' => __('This request has already been decided.')]);
        }

        DB::transaction(function () use ($request, $reviewedBy, $approve, $decisionNotes): void {
            if ($approve) {
                $this->anonymize($request->resident);
            }

            $request->forceFill([
                'status' => $approve ? DataDeletionStatus::Approved : DataDeletionStatus::Denied,
                'reviewed_by_id' => $reviewedBy->id,
                'reviewed_at' => now(),
                'decision_notes' => $decisionNotes,
            ])->save();
        });

        return $request->refresh();
    }

    private function anonymize(Resident $resident): void
    {
        $resident->forceFill([
            'name' => __('Deleted resident'),
            'email' => null,
            'phone' => null,
            'notes' => null,
        ])->save();
        $resident->delete();

        $user = $resident->user;

        if ($user !== null) {
            $user->forceFill(['deactivated_at' => now()])->save();
            SignOutEverywhere::for($user);
        }
    }
}

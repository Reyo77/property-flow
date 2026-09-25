<?php

namespace App\Actions\Governance;

use App\Models\BallotProxy;
use App\Models\BallotVote;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class RevokeProxy
{
    /**
     * @throws ValidationException once the proxy has been used
     */
    public function handle(BallotProxy $proxy, User $revokedBy): void
    {
        if (BallotVote::query()->withoutGlobalScopes()->where('ballot_proxy_id', $proxy->id)->exists()) {
            throw ValidationException::withMessages(['proxy' => __('Your proxy has already voted; the vote stands.')]);
        }

        $proxy->forceFill(['revoked_at' => now()])->save();

        activity('ballots')->performedOn($proxy->ballot)->causedBy($revokedBy)
            ->withProperties(['unit_id' => $proxy->unit_id, 'holder_id' => $proxy->holder_id])
            ->log('proxy revoked');
    }
}

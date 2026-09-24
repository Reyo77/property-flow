<?php

namespace App\Actions\FrontDesk;

use App\Events\FrontDeskActivity;
use App\Models\GuestPass;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RedeemGuestPass
{
    /**
     * Marks the pass used and logs the guest as a visitor in one step, so the desk doesn't have
     * to log the same guest twice.
     *
     * @throws ValidationException
     */
    public function handle(GuestPass $guestPass, User $redeemedBy): Visitor
    {
        $visitor = DB::transaction(function () use ($guestPass, $redeemedBy): Visitor {
            $guestPass = GuestPass::query()->whereKey($guestPass->id)->lockForUpdate()->firstOrFail();

            if (! $guestPass->isValidToday()) {
                throw ValidationException::withMessages(['code' => __('This pass has already been used, expired, or is not yet valid.')]);
            }

            $guestPass->forceFill(['used_at' => now(), 'used_by_id' => $redeemedBy->id])->save();

            $visitor = $guestPass->community->visitors()->make([
                'unit_id' => $guestPass->unit_id,
                'visitor_name' => $guestPass->guest_name,
                'purpose' => __('Guest pass'),
            ]);
            $visitor->forceFill(['company_id' => $guestPass->company_id, 'logged_by_id' => $redeemedBy->id])->save();

            return $visitor;
        });

        FrontDeskActivity::dispatch($visitor->community_id, 'guest_pass', __(':name checked in with a guest pass.', ['name' => $visitor->visitor_name]));

        return $visitor;
    }
}

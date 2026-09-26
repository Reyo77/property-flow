<?php

namespace App\Actions\FrontDesk;

use App\Enums\Permission;
use App\Models\Community;
use App\Models\GuestPass;
use App\Models\Residency;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class IssueGuestPass
{
    /**
     * Creates a pass the guest shows at the front desk. Residents issue passes for their own
     * units; the front desk may issue one for any unit, attached to one of its residents.
     *
     * @param  array{unit_id: int, guest_name: string, valid_from: string, valid_until: string}  $validated
     *
     * @throws ValidationException
     */
    public function handle(Community $community, User $issuedBy, array $validated): GuestPass
    {
        $issuedByTeam = $issuedBy->canAccessCommunity($community) && $issuedBy->hasCompanyPermission(Permission::ManageVisitors);
        $issuedBy->loadMissing('resident');
        $resident = $issuedBy->resident;

        $livesThere = $resident !== null && $resident->residencies()
            ->where('community_id', $community->id)->where('unit_id', $validated['unit_id'])->active()->exists();

        if (! $issuedByTeam && ! $livesThere) {
            throw ValidationException::withMessages(['unit_id' => __('Choose one of your own units.')]);
        }

        $residentId = $livesThere
            ? $resident->id
            : Residency::query()->where('unit_id', $validated['unit_id'])->active()->orderByDesc('is_primary')->value('resident_id');

        if ($residentId === null) {
            throw ValidationException::withMessages(['unit_id' => __('That unit has no resident to attach the pass to.')]);
        }

        return $community->guestPasses()->create([
            'unit_id' => $validated['unit_id'],
            'resident_id' => $residentId,
            'guest_name' => $validated['guest_name'],
            'valid_from' => $validated['valid_from'],
            'valid_until' => $validated['valid_until'],
        ]);
    }
}

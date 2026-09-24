<?php

namespace App\Actions\FrontDesk;

use App\Events\FrontDeskActivity;
use App\Models\Community;
use App\Models\ParkingPermit;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class IssueParkingPermit
{
    /**
     * The plan calls for "limits per unit" without specifying a number; a fixed cap on
     * simultaneously-active permits is the simplest rule that still stops a unit from
     * monopolizing visitor spots, without a whole configurable-limits UI for one number.
     */
    public const int MAX_ACTIVE_PERMITS_PER_UNIT = 3;

    /**
     * @param  array{unit_id: int, plate_number: string, visitor_name: string|null, starts_on: string, ends_on: string, notes: string|null}  $validated
     *
     * @throws ValidationException
     */
    public function handle(Community $community, User $issuedBy, array $validated): ParkingPermit
    {
        $activeCount = ParkingPermit::query()
            ->where('company_id', $community->company_id)
            ->where('unit_id', $validated['unit_id'])
            ->active()
            ->count();

        if ($activeCount >= self::MAX_ACTIVE_PERMITS_PER_UNIT) {
            throw ValidationException::withMessages([
                'unit_id' => __('This unit already has the maximum of :max active permits.', ['max' => self::MAX_ACTIVE_PERMITS_PER_UNIT]),
            ]);
        }

        $permit = $community->parkingPermits()->make($validated);
        $permit->forceFill(['company_id' => $community->company_id, 'issued_by_id' => $issuedBy->id])->save();

        FrontDeskActivity::dispatch($community->id, 'parking_permit', __('Parking permit issued for :plate.', ['plate' => $permit->plate_number]));

        return $permit;
    }
}

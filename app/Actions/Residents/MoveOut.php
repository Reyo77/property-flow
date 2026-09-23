<?php

namespace App\Actions\Residents;

use App\Models\Residency;
use Illuminate\Validation\ValidationException;

class MoveOut
{
    /**
     * End a residency on the given date. A resident who has no other current home loses portal access to this unit.
     *
     * @throws ValidationException
     */
    public function handle(Residency $residency, string $movedOutOn): void
    {
        if ($residency->moved_in_on !== null && $residency->moved_in_on->toDateString() > $movedOutOn) {
            throw ValidationException::withMessages(['movedOutOn' => __('The move-out date cannot be before the move-in date.')]);
        }

        $residency->update(['moved_out_on' => $movedOutOn, 'is_primary' => false]);
    }
}

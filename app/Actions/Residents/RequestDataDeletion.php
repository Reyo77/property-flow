<?php

namespace App\Actions\Residents;

use App\Enums\DataDeletionStatus;
use App\Models\Resident;
use App\Models\ResidentDataDeletionRequest;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class RequestDataDeletion
{
    public function handle(Resident $resident, User $requestedBy, ?string $notes): ResidentDataDeletionRequest
    {
        if ($resident->dataDeletionRequests()->where('status', DataDeletionStatus::Pending)->exists()) {
            throw ValidationException::withMessages([
                'notes' => __('You already have a deletion request awaiting review.'),
            ]);
        }

        $request = new ResidentDataDeletionRequest;
        $request->forceFill([
            'company_id' => $resident->company_id,
            'resident_id' => $resident->id,
            'requested_by_id' => $requestedBy->id,
            'notes' => $notes,
            'status' => DataDeletionStatus::Pending,
        ])->save();

        return $request;
    }
}

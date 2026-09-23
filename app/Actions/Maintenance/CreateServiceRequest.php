<?php

namespace App\Actions\Maintenance;

use App\Models\Community;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateServiceRequest
{
    /**
     * @param  array{title: string, description: string, category: string, priority: string, unit_id: int|null, entry_permission: bool}  $validated
     * @param  list<UploadedFile>  $photos
     */
    public function handle(Community $community, User $reportedBy, array $validated, array $photos = []): ServiceRequest
    {
        return DB::transaction(function () use ($community, $reportedBy, $validated, $photos): ServiceRequest {
            $serviceRequest = $community->serviceRequests()->make($validated);
            $serviceRequest->forceFill([
                'reported_by_user_id' => $reportedBy->id,
                'reported_by_resident_id' => $reportedBy->resident?->id,
            ])->save();

            foreach ($photos as $photo) {
                $this->attachPhoto($serviceRequest, $photo, $reportedBy);
            }

            return $serviceRequest;
        });
    }

    private function attachPhoto(ServiceRequest $serviceRequest, UploadedFile $photo, User $uploadedBy): void
    {
        $diskPath = $photo->storeAs(
            "attachments/{$serviceRequest->company_id}/service-requests/{$serviceRequest->id}",
            Str::uuid().'.'.$photo->getClientOriginalExtension(),
            'local',
        );

        $serviceRequest->attachments()->create([
            'uploaded_by_id' => $uploadedBy->id,
            'disk_path' => $diskPath,
            'original_filename' => $photo->getClientOriginalName(),
            'mime_type' => (string) $photo->getClientMimeType(),
            'size_bytes' => $photo->getSize() ?: 0,
        ]);
    }
}

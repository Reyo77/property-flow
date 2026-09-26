<?php

namespace App\Actions\Maintenance;

use App\Enums\Permission;
use App\Enums\WebhookEvent;
use App\Models\Community;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Support\Webhooks\Webhooks;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateServiceRequest
{
    /**
     * Residents may only report against a unit they live in or own; the team may choose any unit.
     *
     * @param  array{title: string, description: string, category: string, priority: string, unit_id: int|null, entry_permission: bool}  $validated
     * @param  list<UploadedFile>  $photos
     *
     * @throws ValidationException
     */
    public function handle(Community $community, User $reportedBy, array $validated, array $photos = []): ServiceRequest
    {
        if ($validated['unit_id'] !== null && ! $this->mayReportFor($community, $reportedBy, $validated['unit_id'])) {
            throw ValidationException::withMessages(['unit_id' => __('Choose one of your own units.')]);
        }

        $serviceRequest = DB::transaction(function () use ($community, $reportedBy, $validated, $photos): ServiceRequest {
            $serviceRequest = $community->serviceRequests()->make($validated);
            $serviceRequest->forceFill([
                'company_id' => $community->company_id,
                'reported_by_user_id' => $reportedBy->id,
                'reported_by_resident_id' => $reportedBy->resident?->id,
            ])->save();

            foreach ($photos as $photo) {
                $this->attachPhoto($serviceRequest, $photo, $reportedBy);
            }

            return $serviceRequest;
        });

        app(Webhooks::class)->dispatch(WebhookEvent::ServiceRequestCreated, $serviceRequest);

        return $serviceRequest;
    }

    private function mayReportFor(Community $community, User $user, int $unitId): bool
    {
        if ($user->canAccessCommunity($community) && $user->hasCompanyPermission(Permission::ManageServiceRequests)) {
            return true;
        }

        $user->loadMissing('resident');

        return $user->resident !== null
            && $user->resident->residencies()->where('community_id', $community->id)->where('unit_id', $unitId)->active()->exists();
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

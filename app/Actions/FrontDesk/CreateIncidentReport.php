<?php

namespace App\Actions\FrontDesk;

use App\Events\FrontDeskActivity;
use App\Models\Community;
use App\Models\IncidentReport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateIncidentReport
{
    /**
     * @param  array{unit_id: int|null, title: string, description: string, location: string|null, severity: string, occurred_at: string}  $validated
     * @param  list<UploadedFile>  $photos
     */
    public function handle(Community $community, User $reportedBy, array $validated, array $photos = []): IncidentReport
    {
        $incidentReport = DB::transaction(function () use ($community, $reportedBy, $validated, $photos): IncidentReport {
            $incidentReport = $community->incidentReports()->make($validated);
            $incidentReport->forceFill(['company_id' => $community->company_id, 'reported_by_id' => $reportedBy->id])->save();

            foreach ($photos as $photo) {
                $this->attachPhoto($incidentReport, $photo, $reportedBy);
            }

            return $incidentReport;
        });

        FrontDeskActivity::dispatch($community->id, 'incident', __('Incident reported: :title', ['title' => $incidentReport->title]));

        return $incidentReport;
    }

    private function attachPhoto(IncidentReport $incidentReport, UploadedFile $photo, User $uploadedBy): void
    {
        $diskPath = $photo->storeAs(
            "attachments/{$incidentReport->company_id}/incident-reports/{$incidentReport->id}",
            Str::uuid().'.'.$photo->getClientOriginalExtension(),
            'local',
        );

        $attachment = $incidentReport->attachments()->make([
            'uploaded_by_id' => $uploadedBy->id,
            'disk_path' => $diskPath,
            'original_filename' => $photo->getClientOriginalName(),
            'mime_type' => (string) $photo->getClientMimeType(),
            'size_bytes' => $photo->getSize() ?: 0,
        ]);
        $attachment->forceFill(['company_id' => $incidentReport->company_id])->save();
    }
}

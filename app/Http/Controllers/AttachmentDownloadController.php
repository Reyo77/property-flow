<?php

namespace App\Http\Controllers;

use App\Models\ArchitecturalRequest;
use App\Models\Attachment;
use App\Models\Community;
use App\Models\IncidentReport;
use App\Models\ServiceRequest;
use App\Models\Violation;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams an attachment inline (not as a forced download, so images render in the page) after
 * checking that the viewer may see whatever record it is attached to.
 */
class AttachmentDownloadController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Community $community, Attachment $attachment): StreamedResponse
    {
        $attachable = $attachment->attachable;

        abort_unless($attachable instanceof ServiceRequest || $attachable instanceof IncidentReport || $attachable instanceof Violation || $attachable instanceof ArchitecturalRequest, 404);

        $this->authorize('view', $attachable);

        abort_unless(Storage::disk('local')->exists($attachment->disk_path), 404);

        return Storage::disk('local')->response($attachment->disk_path, $attachment->original_filename);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Community;
use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams one specific (possibly older) version of a document, after checking the document's visibility policy.
 */
class DocumentVersionDownloadController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Community $community, Document $document, DocumentVersion $version): StreamedResponse
    {
        $this->authorize('view', $document);

        abort_if($version->document_id !== $document->id, 404);
        abort_unless(Storage::disk('local')->exists($version->disk_path), 404);

        return Storage::disk('local')->download($version->disk_path, $version->original_filename);
    }
}

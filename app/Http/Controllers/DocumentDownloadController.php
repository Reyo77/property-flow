<?php

namespace App\Http\Controllers;

use App\Models\Community;
use App\Models\Document;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a document's current version from private storage after checking the document's visibility policy.
 *
 * This is deliberately an authenticated, policy-checked route rather than a public signed URL,
 * because a signed link would bypass the per-visibility access rules (owners-only, board-only, etc.).
 */
class DocumentDownloadController extends Controller
{
    use AuthorizesRequests;

    /**
     * Download a document
     *
     * The document's current file, if you may see the document. Also linked from `file.download_url`.
     *
     * @group Documents
     *
     * @response 200 scenario="The file" [Binary application/octet-stream data]
     */
    public function __invoke(Community $community, Document $document): StreamedResponse
    {
        $this->authorize('view', $document);

        $version = $document->currentVersion;

        abort_if($version === null, 404);
        abort_unless(Storage::disk('local')->exists($version->disk_path), 404);

        return Storage::disk('local')->download($version->disk_path, $version->original_filename);
    }
}

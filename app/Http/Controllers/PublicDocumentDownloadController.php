<?php

namespace App\Http\Controllers;

use App\Enums\DocumentVisibility;
use App\Models\Community;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Downloads a document from a community's public website. Deliberately separate from
 * {@see DocumentDownloadController}: that one requires a signed-in user and checks the full
 * visibility policy, whereas here there is no user at all, so only Public-visibility documents
 * are ever reachable.
 */
class PublicDocumentDownloadController extends Controller
{
    public function __invoke(Community $community, Document $document): StreamedResponse
    {
        abort_unless($document->community_id === $community->id, 404);
        abort_unless($document->visibility === DocumentVisibility::Public, 404);

        $version = $document->currentVersion;

        abort_if($version === null, 404);
        abort_unless(Storage::disk('local')->exists($version->disk_path), 404);

        return Storage::disk('local')->download($version->disk_path, $version->original_filename);
    }
}

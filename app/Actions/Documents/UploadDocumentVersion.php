<?php

namespace App\Actions\Documents;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Stores an uploaded file as the next version of a document and makes it the current version.
 *
 * Older versions stay on disk and stay downloadable; only the "current" pointer moves.
 */
class UploadDocumentVersion
{
    public function handle(Document $document, UploadedFile $file, ?User $uploadedBy): DocumentVersion
    {
        return DB::transaction(function () use ($document, $file, $uploadedBy): DocumentVersion {
            $nextVersionNumber = $document->versions()->max('version_number') + 1;

            $diskPath = $file->storeAs(
                "documents/{$document->company_id}/{$document->id}",
                $nextVersionNumber.'-'.Str::uuid().'.'.$file->getClientOriginalExtension(),
                'local',
            );

            $version = $document->versions()->create([
                'uploaded_by_id' => $uploadedBy?->id,
                'version_number' => $nextVersionNumber,
                'disk_path' => $diskPath,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => (string) $file->getClientMimeType(),
                'size_bytes' => $file->getSize() ?: 0,
            ]);

            $document->forceFill(['current_version_id' => $version->id])->save();

            return $version;
        });
    }
}

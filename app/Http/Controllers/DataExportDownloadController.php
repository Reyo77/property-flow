<?php

namespace App\Http\Controllers;

use App\Enums\DataExportStatus;
use App\Models\DataExportRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataExportDownloadController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Request $request, DataExportRequest $dataExportRequest): StreamedResponse
    {
        $this->authorize('manageSettings', $dataExportRequest->company);

        abort_unless($dataExportRequest->status === DataExportStatus::Ready && $dataExportRequest->disk_path !== null, 404);
        abort_unless(Storage::disk('local')->exists($dataExportRequest->disk_path), 404);

        return Storage::disk('local')->download($dataExportRequest->disk_path, 'company-data-export.zip');
    }
}

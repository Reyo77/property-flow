<?php

namespace App\Http\Controllers;

use App\Models\Community;
use App\Models\ConsentForm;
use App\Models\ConsentSignature;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shows a drawn signature: to the form's managers, or to the person who signed.
 */
class ConsentSignatureImageController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Request $request, Community $community, ConsentForm $consentForm, ConsentSignature $signature): StreamedResponse
    {
        abort_unless($signature->consent_form_id === $consentForm->id, 404);
        abort_unless($request->user()?->can('manage', $consentForm) || $request->user()?->id === $signature->user_id, 403);
        abort_unless(Storage::disk('local')->exists($signature->signature_disk_path), 404);

        return Storage::disk('local')->response($signature->signature_disk_path);
    }
}

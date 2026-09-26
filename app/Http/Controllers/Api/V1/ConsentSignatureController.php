<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Engagement\SignConsentForm;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\ConsentForm;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * @group Community engagement
 */
class ConsentSignatureController extends Controller
{
    /**
     * Sign a form
     *
     * Once per person, with the name as signed and a drawn signature as a PNG data URL. The form's
     * text is fingerprinted with the signature, so what was agreed to can be proven later.
     *
     * @bodyParam signed_name string required Example: Rita Resident
     * @bodyParam signature string required A `data:image/png;base64,...` image.
     * @bodyParam agreed boolean required Must be true. Example: true
     *
     * @response 201 {"data": {"signed_at": "2026-10-02T14:05:00+00:00"}}
     */
    public function store(Request $request, Community $community, ConsentForm $consentForm, SignConsentForm $sign): JsonResponse
    {
        Gate::authorize('view', $consentForm);

        $validated = $request->validate([
            'signed_name' => ['required', 'string', 'max:255'],
            'signature' => ['required', 'string', 'starts_with:data:image/png;base64,', 'max:500000'],
            'agreed' => ['accepted'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $signature = $sign->handle($consentForm, $user, $validated['signed_name'], $validated['signature'], $request->ip(), $request->userAgent());

        return response()->json(['data' => ['signed_at' => $signature->signed_at->toIso8601String()]], 201);
    }
}

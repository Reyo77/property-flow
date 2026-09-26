<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ConsentFormResource;
use App\Models\Community;
use App\Models\ConsentForm;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Community engagement
 */
class ConsentFormController extends Controller
{
    /**
     * List forms to sign
     *
     * The forms meant for you (owners-only forms aren't shown to tenants).
     *
     * @apiResourceCollection App\Http\Resources\Api\V1\ConsentFormResource
     *
     * @apiResourceModel App\Models\ConsentForm
     */
    public function index(Request $request, Community $community): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [ConsentForm::class, $community]);

        /** @var User $user */
        $user = $request->user();

        return ConsentFormResource::collection(
            $community->consentForms()->latest()->get()->filter(fn (ConsentForm $form) => $user->can('view', $form))->values(),
        );
    }

    /**
     * Show a form
     *
     * `signed_at` is when you signed it, or null.
     *
     * @response {"data": {"id": 1, "title": "Consent to electronic delivery of notices", "body": "I consent to…", "audience": "owners"}, "signed_at": null}
     */
    public function show(Request $request, Community $community, ConsentForm $consentForm): JsonResponse
    {
        Gate::authorize('view', $consentForm);

        return (new ConsentFormResource($consentForm))->additional([
            'signed_at' => $consentForm->signatures()->where('user_id', $request->user()?->getAuthIdentifier())->value('signed_at'),
        ])->response();
    }
}

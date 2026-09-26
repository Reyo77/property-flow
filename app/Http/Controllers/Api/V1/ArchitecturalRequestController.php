<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ArchitecturalRequests\SubmitArchitecturalRequest;
use App\Enums\ArchitecturalRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ArchitecturalRequestResource;
use App\Models\ArchitecturalRequest;
use App\Models\Community;
use App\Models\User;
use App\Support\Api\ApiQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;

/**
 * @group Violations and renovations
 */
class ArchitecturalRequestController extends Controller
{
    /**
     * List renovation requests
     *
     * Owners see their own units'; the board and staff see all.
     *
     * @queryParam filter[status] `submitted`, `under_review`, `approved`, `approved_with_conditions`, `denied` or `withdrawn`. Example: submitted
     *
     * @apiResourceCollection App\Http\Resources\Api\V1\ArchitecturalRequestResource
     *
     * @apiResourceModel App\Models\ArchitecturalRequest paginate=25 with=unit.building
     */
    public function index(Request $request, Community $community): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [ArchitecturalRequest::class, $community]);

        /** @var User $user */
        $user = $request->user();

        return ArchitecturalRequestResource::collection(ApiQuery::paginate(
            $request,
            $community->architecturalRequests()->with('unit.building')->getQuery(),
            filters: ['status' => ApiQuery::enum(ArchitecturalRequestStatus::class, 'status', 'status')],
            sorts: ['created_at'],
            defaultSort: '-created_at',
            visible: fn (ArchitecturalRequest $architecturalRequest) => $user->can('view', $architecturalRequest),
        ));
    }

    /**
     * Ask to renovate
     *
     * Owners only, for a unit they own. Send as `multipart/form-data` to attach plans.
     *
     * @bodyParam unit_id integer required Example: 1
     * @bodyParam title string required Example: Replace carpet with engineered hardwood
     * @bodyParam description string required Example: Living room and hallway, about 45 m².
     * @bodyParam contractor string Example: Northern Floors Ltd.
     * @bodyParam planned_start_on string YYYY-MM-DD, today or later. Example: 2026-11-02
     * @bodyParam plans file[] Up to 10 PDFs or images, 20 MB each.
     */
    #[ResponseFromApiResource(ArchitecturalRequestResource::class, ArchitecturalRequest::class, status: 201, with: ['unit.building'])]
    public function store(Request $request, Community $community, SubmitArchitecturalRequest $submit): JsonResponse
    {
        Gate::authorize('create', [ArchitecturalRequest::class, $community]);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'contractor' => ['nullable', 'string', 'max:255'],
            'planned_start_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'plans' => ['array', 'max:10'],
            'plans.*' => ['file', 'mimes:pdf,jpg,jpeg,png,heic', 'max:20480'],
        ]);

        /** @var User $user */
        $user = $request->user();
        /** @var list<UploadedFile> $plans */
        $plans = array_values($request->file('plans', []));

        $architecturalRequest = $submit->handle(
            $community->units()->findOrFail((int) $validated['unit_id']),
            $user,
            $validated['title'],
            $validated['description'],
            $validated['contractor'] ?? null,
            isset($validated['planned_start_on']) ? CarbonImmutable::parse($validated['planned_start_on']) : null,
            $plans,
        );

        return (new ArchitecturalRequestResource($architecturalRequest->load('unit.building')))->response()->setStatusCode(201);
    }

    /**
     * Show a renovation request
     *
     * Once decided, `decision_letter_url` is the letter to keep on file.
     *
     * @apiResource App\Http\Resources\Api\V1\ArchitecturalRequestResource
     *
     * @apiResourceModel App\Models\ArchitecturalRequest with=unit.building
     */
    public function show(Community $community, ArchitecturalRequest $architecturalRequest): ArchitecturalRequestResource
    {
        Gate::authorize('view', $architecturalRequest);

        return new ArchitecturalRequestResource($architecturalRequest->load('unit.building'));
    }
}

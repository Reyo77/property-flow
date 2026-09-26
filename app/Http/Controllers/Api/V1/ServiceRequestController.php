<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Maintenance\CreateServiceRequest;
use App\Actions\Maintenance\TransitionServiceRequestStatus;
use App\Concerns\ServiceRequestValidationRules;
use App\Enums\ServiceRequestCategory;
use App\Enums\ServiceRequestPriority;
use App\Enums\ServiceRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ServiceRequestResource;
use App\Models\Community;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Support\Api\ApiQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use LogicException;

/**
 * @group Maintenance
 *
 * Residents report problems in their units or common areas and follow them to resolution; the
 * team triages them. Residents see only their own units' requests and comments not marked
 * internal.
 */
class ServiceRequestController extends Controller
{
    use ServiceRequestValidationRules;

    /**
     * List service requests
     *
     * @queryParam filter[status] `open`, `assigned`, `in_progress`, `on_hold`, `resolved` or `closed`. Example: open
     * @queryParam filter[category] `plumbing`, `electrical`, `hvac`, `appliance`, `structural`, `pest`, `common_area` or `other`. Example: plumbing
     * @queryParam filter[priority] `low`, `medium`, `high` or `urgent`. Example: high
     * @queryParam filter[unit_id] integer Only this unit. Example: 1
     * @queryParam sort Sort by `created_at`, `updated_at` or `priority`. Example: -created_at
     *
     * @apiResourceCollection App\Http\Resources\Api\V1\ServiceRequestResource
     *
     * @apiResourceModel App\Models\ServiceRequest paginate=25 with=unit.building
     */
    public function index(Request $request, Community $community): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [ServiceRequest::class, $community]);

        /** @var User $user */
        $user = $request->user();

        return ServiceRequestResource::collection(ApiQuery::paginate(
            $request,
            $community->serviceRequests()->with('unit.building')->getQuery(),
            filters: [
                'status' => ApiQuery::enum(ServiceRequestStatus::class, 'status', 'status'),
                'category' => ApiQuery::enum(ServiceRequestCategory::class, 'category', 'category'),
                'priority' => ApiQuery::enum(ServiceRequestPriority::class, 'priority', 'priority'),
                'unit_id' => fn (Builder $query, string $value) => $query->where('unit_id', (int) $value),
            ],
            sorts: ['created_at', 'updated_at', 'priority'],
            defaultSort: '-created_at',
            visible: fn (ServiceRequest $serviceRequest) => $user->can('view', $serviceRequest),
        ));
    }

    /**
     * Report a problem
     *
     * Send as `multipart/form-data` to attach photos. Residents may only choose a unit they live
     * in or own (or none, for common areas).
     *
     * @bodyParam title string required Example: Kitchen tap dripping
     * @bodyParam description string required Example: Constant drip from the hot tap since Monday.
     * @bodyParam category string required `plumbing`, `electrical`, `hvac`, `appliance`, `structural`, `pest`, `common_area` or `other`. Example: plumbing
     * @bodyParam priority string required `low`, `medium`, `high` or `urgent`. Example: medium
     * @bodyParam unit_id integer The unit, or null for a common area. Example: 1
     * @bodyParam entry_permission boolean Staff may enter if nobody is home. Example: true
     * @bodyParam photos file[] Up to 6 images, 8 MB each.
     */
    #[ResponseFromApiResource(ServiceRequestResource::class, ServiceRequest::class, status: 201)]
    public function store(Request $request, Community $community, CreateServiceRequest $createServiceRequest): JsonResponse
    {
        Gate::authorize('create', [ServiceRequest::class, $community]);

        $validated = $request->validate($this->serviceRequestRules($community));

        /** @var User $user */
        $user = $request->user();
        /** @var list<UploadedFile> $photos */
        $photos = array_values($request->file('photos', []));

        $serviceRequest = $createServiceRequest->handle($community, $user, [
            'title' => $validated['title'],
            'description' => $validated['description'],
            'category' => $validated['category'],
            'priority' => $validated['priority'],
            'unit_id' => isset($validated['unit_id']) ? (int) $validated['unit_id'] : null,
            'entry_permission' => (bool) ($validated['entry_permission'] ?? false),
        ], $photos);

        return (new ServiceRequestResource($serviceRequest->load('unit.building')))->response()->setStatusCode(201);
    }

    /**
     * Show a service request
     *
     * Includes the comments you can see.
     *
     * @apiResource App\Http\Resources\Api\V1\ServiceRequestResource
     *
     * @apiResourceModel App\Models\ServiceRequest with=unit.building,comments.author
     */
    public function show(Request $request, Community $community, ServiceRequest $serviceRequest): ServiceRequestResource
    {
        Gate::authorize('view', $serviceRequest);

        $seesInternal = $request->user()?->can('addInternalComment', $serviceRequest) === true;

        return new ServiceRequestResource($serviceRequest->load([
            'unit.building',
            'comments' => fn ($query) => $query->when(! $seesInternal, fn ($query) => $query->where('visible_to_resident', true))->with('author')->oldest(),
        ]));
    }

    /**
     * Change the status
     *
     * Team only. Moves the request along its workflow; an impossible step (say, from `closed`
     * back to `open`) is refused with a 422.
     *
     * @bodyParam status string required The new status. Example: assigned
     *
     * @apiResource App\Http\Resources\Api\V1\ServiceRequestResource
     *
     * @apiResourceModel App\Models\ServiceRequest with=unit.building
     */
    public function update(Request $request, Community $community, ServiceRequest $serviceRequest, TransitionServiceRequestStatus $transition): ServiceRequestResource
    {
        Gate::authorize('manage', $serviceRequest);

        $validated = $request->validate(['status' => ['required', Rule::enum(ServiceRequestStatus::class)]]);

        try {
            $transition->handle($serviceRequest, ServiceRequestStatus::from($validated['status']));
        } catch (LogicException $exception) {
            throw ValidationException::withMessages(['status' => $exception->getMessage()]);
        }

        return new ServiceRequestResource($serviceRequest->refresh()->load('unit.building'));
    }
}

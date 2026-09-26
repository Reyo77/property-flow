<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Violations\ReportViolation;
use App\Enums\ViolationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ViolationResource;
use App\Models\Community;
use App\Models\Unit;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationRule;
use App\Support\Api\ApiQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;

/**
 * @group Violations and renovations
 *
 * Bylaw violations escalate on a schedule (courtesy notice, warning, fines); owners see those on
 * their own units. Owners ask the board before renovating; the decision comes with a letter.
 */
class ViolationController extends Controller
{
    /**
     * List violations
     *
     * Owners see their own units'; tenants see none.
     *
     * @queryParam filter[status] `open`, `resolved` or `dismissed`. Example: open
     * @queryParam sort `observed_at`. Example: -observed_at
     *
     * @apiResourceCollection App\Http\Resources\Api\V1\ViolationResource
     *
     * @apiResourceModel App\Models\Violation paginate=25 with=rule,unit.building
     */
    public function index(Request $request, Community $community): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Violation::class, $community]);

        /** @var User $user */
        $user = $request->user();

        return ViolationResource::collection(ApiQuery::paginate(
            $request,
            $community->violations()->with(['rule', 'unit.building'])->getQuery(),
            filters: ['status' => ApiQuery::enum(ViolationStatus::class, 'status', 'status')],
            sorts: ['observed_at'],
            defaultSort: '-observed_at',
            visible: fn (Violation $violation) => $user->can('view', $violation),
        ));
    }

    /**
     * Report a violation
     *
     * Staff only. A courtesy notice goes to the unit's owners at once. Send as
     * `multipart/form-data` to attach photos.
     *
     * @bodyParam violation_rule_id integer required Example: 3
     * @bodyParam unit_id integer required Example: 1
     * @bodyParam description string required Example: Dog off leash in the lobby.
     * @bodyParam location string Example: Lobby
     * @bodyParam observed_at string ISO 8601; defaults to now. Example: 2026-10-01T08:00:00-04:00
     * @bodyParam photos file[] Up to 6 images.
     */
    #[ResponseFromApiResource(ViolationResource::class, Violation::class, status: 201, with: ['rule', 'unit.building', 'notices'])]
    public function store(Request $request, Community $community, ReportViolation $reportViolation): JsonResponse
    {
        Gate::authorize('create', [Violation::class, $community]);

        $validated = $request->validate([
            'violation_rule_id' => ['required', 'integer', Rule::exists(ViolationRule::class, 'id')->where('community_id', $community->id)->where('is_active', true)],
            'unit_id' => ['required', 'integer', Rule::exists(Unit::class, 'id')->where('community_id', $community->id)->withoutTrashed()],
            'description' => ['required', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:255'],
            'observed_at' => ['nullable', 'date', 'before_or_equal:now'],
            'photos' => ['array', 'max:6'],
            'photos.*' => ['image', 'max:8192'],
        ]);

        /** @var User $user */
        $user = $request->user();
        /** @var list<UploadedFile> $photos */
        $photos = array_values($request->file('photos', []));

        $violation = $reportViolation->handle(
            $community->violationRules()->findOrFail((int) $validated['violation_rule_id']),
            $community->units()->findOrFail((int) $validated['unit_id']),
            isset($validated['observed_at']) ? CarbonImmutable::parse($validated['observed_at'], $community->timezone) : CarbonImmutable::now(),
            $validated['description'],
            $validated['location'] ?? null,
            $photos,
            $user,
        );

        return (new ViolationResource($violation->load(['rule', 'unit.building', 'notices'])))->response()->setStatusCode(201);
    }

    /**
     * Show a violation
     *
     * With its notices; each notice's `letter_url` is the PDF sent to the owners.
     *
     * @apiResource App\Http\Resources\Api\V1\ViolationResource
     *
     * @apiResourceModel App\Models\Violation with=rule,unit.building,notices
     */
    public function show(Community $community, Violation $violation): ViolationResource
    {
        Gate::authorize('view', $violation);

        return new ViolationResource($violation->load(['rule', 'unit.building', 'notices']));
    }
}

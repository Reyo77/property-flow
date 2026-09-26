<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\FrontDesk\CreateIncidentReport;
use App\Concerns\IncidentReportValidationRules;
use App\Enums\IncidentSeverity;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\IncidentReportResource;
use App\Models\Community;
use App\Models\IncidentReport;
use App\Models\User;
use App\Support\Api\ApiQuery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;

/**
 * @group Front desk
 */
class IncidentReportController extends Controller
{
    use IncidentReportValidationRules;

    /**
     * List incident reports
     *
     * @queryParam filter[severity] `low`, `medium`, `high` or `critical`. Example: high
     * @queryParam filter[open] `1` for unresolved reports. Example: 1
     * @queryParam sort `occurred_at`. Example: -occurred_at
     *
     * @apiResourceCollection App\Http\Resources\Api\V1\IncidentReportResource
     *
     * @apiResourceModel App\Models\IncidentReport paginate=25 with=unit.building
     */
    public function index(Request $request, Community $community): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [IncidentReport::class, $community]);

        return IncidentReportResource::collection(ApiQuery::paginate(
            $request,
            $community->incidentReports()->with('unit.building')->getQuery(),
            filters: [
                'severity' => ApiQuery::enum(IncidentSeverity::class, 'severity', 'severity'),
                'open' => fn (Builder $query, string $value) => $value === '1' ? $query->whereNull('resolved_at') : null,
            ],
            sorts: ['occurred_at'],
            defaultSort: '-occurred_at',
        ));
    }

    /**
     * File an incident report
     *
     * Send as `multipart/form-data` to attach photos.
     *
     * @bodyParam title string required Example: Water leak in parking P2
     * @bodyParam description string required Example: Water coming through the ceiling near spot 41.
     * @bodyParam severity string required `low`, `medium`, `high` or `critical`. Example: high
     * @bodyParam occurred_at string required ISO 8601; without an offset it is taken as the community's local time. Example: 2026-10-02T22:15:00-04:00
     * @bodyParam unit_id integer Example: 1
     * @bodyParam location string Example: P2, near spot 41
     * @bodyParam photos file[] Up to 6 images, 8 MB each.
     */
    #[ResponseFromApiResource(IncidentReportResource::class, IncidentReport::class, status: 201, with: ['unit.building'])]
    public function store(Request $request, Community $community, CreateIncidentReport $create): JsonResponse
    {
        Gate::authorize('create', [IncidentReport::class, $community]);

        $validated = $request->validate($this->incidentReportRules($community));

        /** @var User $user */
        $user = $request->user();
        /** @var list<UploadedFile> $photos */
        $photos = array_values($request->file('photos', []));
        unset($validated['photos']);
        // ISO 8601; without an offset it is read on the community's own clock.
        $validated['occurred_at'] = CarbonImmutable::parse($validated['occurred_at'], $community->timezone)->utc()->toDateTimeString();

        $report = $create->handle($community, $user, $validated, $photos);

        return (new IncidentReportResource($report->load('unit.building')))->response()->setStatusCode(201);
    }

    /**
     * Show an incident report
     *
     * @apiResource App\Http\Resources\Api\V1\IncidentReportResource
     *
     * @apiResourceModel App\Models\IncidentReport with=unit.building
     */
    public function show(Community $community, IncidentReport $incidentReport): IncidentReportResource
    {
        Gate::authorize('view', $incidentReport);

        return new IncidentReportResource($incidentReport->load('unit.building'));
    }
}

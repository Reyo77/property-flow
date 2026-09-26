<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\FrontDesk\IssueParkingPermit;
use App\Concerns\ParkingPermitValidationRules;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ParkingPermitResource;
use App\Models\Community;
use App\Models\ParkingPermit;
use App\Models\User;
use App\Support\Api\ApiQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * @group Front desk
 */
class ParkingPermitController extends Controller
{
    use ParkingPermitValidationRules;

    /**
     * List parking permits
     *
     * @queryParam filter[plate] Plate number contains. Example: ABC
     * @queryParam filter[active] `1` for permits valid today. Example: 1
     *
     * @apiResourceCollection App\Http\Resources\Api\V1\ParkingPermitResource
     *
     * @apiResourceModel App\Models\ParkingPermit paginate=25 with=unit.building
     */
    public function index(Request $request, Community $community): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [ParkingPermit::class, $community]);

        return ParkingPermitResource::collection(ApiQuery::paginate(
            $request,
            $community->parkingPermits()->with('unit.building')->getQuery(),
            filters: [
                'plate' => fn (Builder $query, string $value) => $query->where('plate_number', 'like', '%'.$value.'%'),
                'active' => fn (Builder $query, string $value) => $value === '1' ? $query->whereDate('starts_on', '<=', today())->whereDate('ends_on', '>=', today()) : null,
            ],
            sorts: ['ends_on', 'starts_on'],
            defaultSort: '-ends_on',
        ));
    }

    /**
     * Issue a parking permit
     *
     * @bodyParam unit_id integer required Example: 1
     * @bodyParam plate_number string required Example: ABCD 123
     * @bodyParam visitor_name string Example: Sam Guest
     * @bodyParam starts_on string required Example: 2026-10-03
     * @bodyParam ends_on string required Example: 2026-10-05
     * @bodyParam notes string Example: Blue hatchback
     *
     * @apiResource 201 App\Http\Resources\Api\V1\ParkingPermitResource
     *
     * @apiResourceModel App\Models\ParkingPermit with=unit.building
     */
    public function store(Request $request, Community $community, IssueParkingPermit $issue): JsonResponse
    {
        Gate::authorize('create', [ParkingPermit::class, $community]);

        /** @var User $user */
        $user = $request->user();
        $permit = $issue->handle($community, $user, $request->validate($this->parkingPermitRules($community)));

        return (new ParkingPermitResource($permit->load('unit.building')))->response()->setStatusCode(201);
    }

    /**
     * Revoke a parking permit
     *
     * @response 204 scenario="Revoked"
     */
    public function destroy(Community $community, ParkingPermit $parkingPermit): Response
    {
        Gate::authorize('delete', $parkingPermit);

        $parkingPermit->delete();

        return response()->noContent();
    }
}

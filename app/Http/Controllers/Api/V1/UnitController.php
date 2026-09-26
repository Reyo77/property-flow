<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UnitResource;
use App\Models\Community;
use App\Models\Unit;
use App\Support\Api\ApiQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Property
 */
class UnitController extends Controller
{
    /**
     * List units
     *
     * @queryParam filter[building_id] integer Only units in this building. Example: 1
     * @queryParam filter[search] string Unit number contains. Example: 10
     * @queryParam sort Sort by `number` or `id`. Example: number
     *
     * @apiResourceCollection App\Http\Resources\Api\V1\UnitResource
     *
     * @apiResourceModel App\Models\Unit paginate=25 with=building
     */
    public function index(Request $request, Community $community): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Unit::class, $community]);

        return UnitResource::collection(ApiQuery::paginate(
            $request,
            $community->units()->with('building')->getQuery(),
            filters: [
                'building_id' => fn (Builder $query, string $value) => $query->where('building_id', (int) $value),
                'search' => fn (Builder $query, string $value) => $query->where('number', 'like', '%'.$value.'%'),
            ],
            sorts: ['number'],
            defaultSort: 'number',
        ));
    }

    /**
     * Show a unit
     *
     * @apiResource App\Http\Resources\Api\V1\UnitResource
     *
     * @apiResourceModel App\Models\Unit with=building
     */
    public function show(Community $community, Unit $unit): UnitResource
    {
        Gate::authorize('view', $unit);

        return new UnitResource($unit->load('building'));
    }
}

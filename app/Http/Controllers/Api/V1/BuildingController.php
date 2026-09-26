<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BuildingResource;
use App\Models\Building;
use App\Models\Community;
use App\Support\Api\ApiQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Property
 */
class BuildingController extends Controller
{
    /**
     * List buildings
     *
     * @queryParam sort Sort by `name` or `id` (prefix `-` for descending). Example: name
     *
     * @apiResourceCollection App\Http\Resources\Api\V1\BuildingResource
     *
     * @apiResourceModel App\Models\Building paginate=25
     */
    public function index(Request $request, Community $community): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Building::class, $community]);

        return BuildingResource::collection(ApiQuery::paginate($request, $community->buildings()->getQuery(), sorts: ['name'], defaultSort: 'name'));
    }
}

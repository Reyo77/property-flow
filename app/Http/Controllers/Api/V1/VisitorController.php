<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\FrontDesk\LogVisitor;
use App\Concerns\VisitorValidationRules;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\VisitorResource;
use App\Models\Community;
use App\Models\User;
use App\Models\Visitor;
use App\Support\Api\ApiQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Front desk
 */
class VisitorController extends Controller
{
    use VisitorValidationRules;

    /**
     * List visitors
     *
     * @queryParam filter[on_site] `1` for visitors not yet checked out. Example: 1
     * @queryParam sort `checked_in_at`. Example: -checked_in_at
     *
     * @apiResourceCollection App\Http\Resources\Api\V1\VisitorResource
     *
     * @apiResourceModel App\Models\Visitor paginate=25 with=unit.building
     */
    public function index(Request $request, Community $community): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Visitor::class, $community]);

        return VisitorResource::collection(ApiQuery::paginate(
            $request,
            $community->visitors()->with('unit.building')->getQuery(),
            filters: ['on_site' => fn (Builder $query, string $value) => $value === '1' ? $query->whereNull('checked_out_at') : null],
            sorts: ['checked_in_at'],
            defaultSort: '-checked_in_at',
        ));
    }

    /**
     * Check a visitor in
     *
     * @bodyParam visitor_name string required Example: Sam Guest
     * @bodyParam unit_id integer The unit they're visiting. Example: 1
     * @bodyParam purpose string Example: Dinner
     * @bodyParam notes string Example: Parked in visitor spot 4
     *
     * @apiResource 201 App\Http\Resources\Api\V1\VisitorResource
     *
     * @apiResourceModel App\Models\Visitor with=unit.building
     */
    public function store(Request $request, Community $community, LogVisitor $logVisitor): JsonResponse
    {
        Gate::authorize('create', [Visitor::class, $community]);

        /** @var User $user */
        $user = $request->user();
        $visitor = $logVisitor->handle($community, $user, $request->validate($this->visitorRules($community)));

        return (new VisitorResource($visitor->load('unit.building')))->response()->setStatusCode(201);
    }

    /**
     * Check a visitor out
     *
     * @bodyParam checked_out boolean required Must be true. Example: true
     *
     * @apiResource App\Http\Resources\Api\V1\VisitorResource
     *
     * @apiResourceModel App\Models\Visitor with=unit.building
     */
    public function update(Request $request, Community $community, Visitor $visitor): VisitorResource
    {
        Gate::authorize('update', $visitor);

        $request->validate(['checked_out' => ['required', 'accepted']]);

        if ($visitor->checked_out_at === null) {
            $visitor->update(['checked_out_at' => now()]);
        }

        return new VisitorResource($visitor->load('unit.building'));
    }
}

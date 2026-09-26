<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ResidentResource;
use App\Models\Community;
use App\Models\Residency;
use App\Models\Resident;
use App\Support\Api\ApiQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * @group Residents
 *
 * The community's owners and tenants (team only). Each resident comes with their residencies in
 * this community.
 */
class ResidentController extends Controller
{
    /**
     * List residents
     *
     * @queryParam filter[status] Whose residencies to include: `current` (default), `past` or `all`. Example: current
     * @queryParam filter[type] `owner` or `tenant`. Example: owner
     * @queryParam filter[search] Name, email or phone contains. Example: rita
     * @queryParam sort Sort by `name` or `id`. Example: name
     *
     * @apiResourceCollection App\Http\Resources\Api\V1\ResidentResource
     *
     * @apiResourceModel App\Models\Resident paginate=25 with=residencies.unit
     */
    public function index(Request $request, Community $community): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Resident::class, $community]);

        $status = $request->input('filter.status', 'current');
        $type = $request->input('filter.type');

        $residencies = fn (Builder $query) => $this->matchingResidencies($query, $community, $status, $type);

        $residents = ApiQuery::paginate(
            $request,
            Resident::query()->whereHas('residencies', $residencies),
            filters: [
                'status' => fn (Builder $query, string $value) => in_array($value, ['current', 'past', 'all'], true) ? null : throw ValidationException::withMessages(['filter.status' => __('Use current, past or all.')]),
                'type' => fn (Builder $query, string $value) => in_array($value, ['owner', 'tenant'], true) ? null : throw ValidationException::withMessages(['filter.type' => __('Use owner or tenant.')]),
                'search' => fn (Builder $query, string $value) => $query->where(fn (Builder $query) => $query->where('name', 'like', "%{$value}%")->orWhere('email', 'like', "%{$value}%")->orWhere('phone', 'like', "%{$value}%")),
            ],
            sorts: ['name'],
            defaultSort: 'name',
        );

        // Only the residencies that matched, loaded in one query for the whole page.
        $matching = Residency::query()->whereIn('resident_id', $residents->getCollection()->modelKeys())->with('unit.building');
        $residencies($matching);
        $byResident = $matching->get()->groupBy('resident_id');

        foreach ($residents->getCollection() as $resident) {
            $resident->setRelation('residencies', $byResident->get($resident->id) ?? new Collection);
        }

        return ResidentResource::collection($residents);
    }

    /**
     * Show a resident
     *
     * @apiResource App\Http\Resources\Api\V1\ResidentResource
     *
     * @apiResourceModel App\Models\Resident with=residencies.unit
     */
    public function show(Community $community, Resident $resident): ResidentResource
    {
        Gate::authorize('view', [$resident, $community]);

        return new ResidentResource($resident->load(['residencies' => fn ($query) => $query->where('community_id', $community->id)->with('unit.building')]));
    }

    /**
     * The community's residencies that match the status and type filters.
     *
     * @param  Builder<Residency>  $query
     */
    private function matchingResidencies(Builder $query, Community $community, mixed $status, mixed $type): void
    {
        $query->where('community_id', $community->id);

        match ($status) {
            'past' => $query->past(),
            'all' => null,
            default => $query->active(),
        };

        if (is_string($type)) {
            $query->where('type', $type);
        }
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\FrontDesk\LogPackage;
use App\Concerns\PackageValidationRules;
use App\Enums\PackageStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PackageResource;
use App\Models\Community;
use App\Models\Package;
use App\Models\User;
use App\Support\Api\ApiQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;

/**
 * @group Front desk
 *
 * Packages, visitors, guest passes, parking permits and incident reports. Residents see their
 * own packages and guest passes; the rest is for the front desk and security team.
 */
class PackageController extends Controller
{
    use PackageValidationRules;

    /**
     * List packages
     *
     * @queryParam filter[status] `awaiting_pickup` or `picked_up`. Example: awaiting_pickup
     * @queryParam sort `created_at` or `released_at`. Example: -created_at
     *
     * @apiResourceCollection App\Http\Resources\Api\V1\PackageResource
     *
     * @apiResourceModel App\Models\Package paginate=25 with=unit.building,resident
     */
    public function index(Request $request, Community $community): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Package::class, $community]);

        /** @var User $user */
        $user = $request->user();

        return PackageResource::collection(ApiQuery::paginate(
            $request,
            $community->packages()->with(['unit.building', 'resident'])->getQuery(),
            filters: ['status' => ApiQuery::enum(PackageStatus::class, 'status', 'status')],
            sorts: ['created_at', 'released_at'],
            defaultSort: '-created_at',
            visible: fn (Package $package) => $user->can('view', $package),
        ));
    }

    /**
     * Log a package
     *
     * Front desk only. The recipient is notified.
     *
     * @bodyParam unit_id integer Example: 1
     * @bodyParam resident_id integer The recipient. Example: 5
     * @bodyParam carrier string required Example: Canada Post
     * @bodyParam tracking_number string Example: 1Z999AA10123456784
     * @bodyParam shelf_location string Example: B3
     */
    #[ResponseFromApiResource(PackageResource::class, Package::class, status: 201, with: ['unit.building', 'resident'])]
    public function store(Request $request, Community $community, LogPackage $logPackage): JsonResponse
    {
        Gate::authorize('create', [Package::class, $community]);

        $validated = $request->validate($this->packageRules($community));

        /** @var User $user */
        $user = $request->user();
        $package = $logPackage->handle($community, $user, [
            'unit_id' => isset($validated['unit_id']) ? (int) $validated['unit_id'] : null,
            'resident_id' => isset($validated['resident_id']) ? (int) $validated['resident_id'] : null,
            'carrier' => $validated['carrier'],
            'tracking_number' => $validated['tracking_number'] ?? null,
            'shelf_location' => $validated['shelf_location'] ?? null,
        ]);

        return (new PackageResource($package->load(['unit.building', 'resident'])))->response()->setStatusCode(201);
    }

    /**
     * Show a package
     *
     * @apiResource App\Http\Resources\Api\V1\PackageResource
     *
     * @apiResourceModel App\Models\Package with=unit.building,resident
     */
    public function show(Community $community, Package $package): PackageResource
    {
        Gate::authorize('view', $package);

        return new PackageResource($package->load(['unit.building', 'resident']));
    }
}

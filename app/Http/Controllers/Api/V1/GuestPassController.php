<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\FrontDesk\IssueGuestPass;
use App\Concerns\VisitorValidationRules;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\GuestPassResource;
use App\Models\Community;
use App\Models\GuestPass;
use App\Models\User;
use App\Support\Api\ApiQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;

/**
 * @group Front desk
 */
class GuestPassController extends Controller
{
    use VisitorValidationRules;

    /**
     * List guest passes
     *
     * Residents see the passes they issued; the front desk sees all.
     *
     * @queryParam filter[valid] `1` for passes not yet used or expired. Example: 1
     *
     * @apiResourceCollection App\Http\Resources\Api\V1\GuestPassResource
     *
     * @apiResourceModel App\Models\GuestPass paginate=25 with=unit.building
     */
    public function index(Request $request, Community $community): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [GuestPass::class, $community]);

        /** @var User $user */
        $user = $request->user();

        return GuestPassResource::collection(ApiQuery::paginate(
            $request,
            $community->guestPasses()->with('unit.building')->getQuery(),
            filters: ['valid' => fn (Builder $query, string $value) => $value === '1' ? $query->whereNull('used_at')->where('valid_until', '>=', now()) : null],
            sorts: ['valid_until', 'created_at'],
            defaultSort: '-valid_until',
            visible: fn (GuestPass $guestPass) => $user->can('view', $guestPass),
        ));
    }

    /**
     * Issue a guest pass
     *
     * Residents issue passes for their own units; the guest shows the `code` at the front desk.
     *
     * @bodyParam unit_id integer required Example: 1
     * @bodyParam guest_name string required Example: Sam Guest
     * @bodyParam valid_from string required Example: 2026-10-03
     * @bodyParam valid_until string required Example: 2026-10-05
     */
    #[ResponseFromApiResource(GuestPassResource::class, GuestPass::class, status: 201, with: ['unit.building'])]
    public function store(Request $request, Community $community, IssueGuestPass $issueGuestPass): JsonResponse
    {
        Gate::authorize('create', [GuestPass::class, $community]);

        $validated = $request->validate($this->guestPassRules($community));

        /** @var User $user */
        $user = $request->user();
        $guestPass = $issueGuestPass->handle($community, $user, [
            'unit_id' => (int) $validated['unit_id'],
            'guest_name' => $validated['guest_name'],
            'valid_from' => $validated['valid_from'],
            'valid_until' => $validated['valid_until'],
        ]);

        return (new GuestPassResource($guestPass->load('unit.building')))->response()->setStatusCode(201);
    }

    /**
     * Show a guest pass
     *
     * @apiResource App\Http\Resources\Api\V1\GuestPassResource
     *
     * @apiResourceModel App\Models\GuestPass with=unit.building
     */
    public function show(Community $community, GuestPass $guestPass): GuestPassResource
    {
        Gate::authorize('view', $guestPass);

        return new GuestPassResource($guestPass->load('unit.building'));
    }
}

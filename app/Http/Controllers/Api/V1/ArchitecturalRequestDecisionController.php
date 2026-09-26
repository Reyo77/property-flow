<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ArchitecturalRequests\DecideArchitecturalRequest;
use App\Enums\ArchitecturalRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ArchitecturalRequestResource;
use App\Models\ArchitecturalRequest;
use App\Models\Community;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * @group Violations and renovations
 */
class ArchitecturalRequestDecisionController extends Controller
{
    /**
     * Decide a renovation request
     *
     * The board only. Approving with conditions needs `conditions`; denying needs `notes`.
     *
     * @bodyParam decision string required `approved`, `approved_with_conditions` or `denied`. Example: approved_with_conditions
     * @bodyParam conditions string Required for `approved_with_conditions`. Example: Acoustic underlay required.
     * @bodyParam notes string Required for `denied`. Example: Not permitted by the declaration.
     *
     * @apiResource App\Http\Resources\Api\V1\ArchitecturalRequestResource
     *
     * @apiResourceModel App\Models\ArchitecturalRequest with=unit.building
     */
    public function store(Request $request, Community $community, ArchitecturalRequest $architecturalRequest, DecideArchitecturalRequest $decide): ArchitecturalRequestResource
    {
        Gate::authorize('decide', $architecturalRequest);

        $validated = $request->validate([
            'decision' => ['required', Rule::in([ArchitecturalRequestStatus::Approved->value, ArchitecturalRequestStatus::ApprovedWithConditions->value, ArchitecturalRequestStatus::Denied->value])],
            'conditions' => ['nullable', 'required_if:decision,approved_with_conditions', 'string', 'max:2000'],
            'notes' => ['nullable', 'required_if:decision,denied', 'string', 'max:2000'],
        ]);

        /** @var User $user */
        $user = $request->user();

        try {
            $decide->decide($architecturalRequest, ArchitecturalRequestStatus::from($validated['decision']), $validated['conditions'] ?? null, $validated['notes'] ?? null, $user);
        } catch (LogicException $exception) {
            throw ValidationException::withMessages(['decision' => $exception->getMessage()]);
        }

        return new ArchitecturalRequestResource($architecturalRequest->refresh()->load('unit.building'));
    }
}

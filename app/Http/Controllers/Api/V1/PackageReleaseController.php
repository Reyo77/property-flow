<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\FrontDesk\ReleasePackage;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PackageResource;
use App\Models\Community;
use App\Models\Package;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * @group Front desk
 */
class PackageReleaseController extends Controller
{
    /**
     * Release a package
     *
     * Front desk only: hand it over, optionally with the recipient's signature as a PNG data URL.
     *
     * @bodyParam released_to_name string required Who collected it. Example: Rita Resident
     * @bodyParam signature string A `data:image/png;base64,...` signature.
     *
     * @apiResource App\Http\Resources\Api\V1\PackageResource
     *
     * @apiResourceModel App\Models\Package with=unit.building,resident
     */
    public function store(Request $request, Community $community, Package $package, ReleasePackage $releasePackage): PackageResource
    {
        Gate::authorize('release', $package);

        $validated = $request->validate([
            'released_to_name' => ['required', 'string', 'max:255'],
            'signature' => ['nullable', 'string', 'starts_with:data:image/png;base64,', 'max:500000'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $releasePackage->handle($package, $user, $validated['released_to_name'], $validated['signature'] ?? null);

        return new PackageResource($package->refresh()->load(['unit.building', 'resident']));
    }
}

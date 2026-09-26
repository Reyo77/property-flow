<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Residency;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Authentication
 */
class MeController extends Controller
{
    /**
     * Who am I
     *
     * The signed-in person, what they can do, and where: `communities` are the ones they work in
     * as a team member (empty for residents), `homes` the units they live in or own. `permissions`
     * lists the company permissions they hold, so an app can show or hide features.
     *
     * @response {"data": {"id": 5, "name": "Rita Resident", "email": "resident@propertyflow.test", "company": {"id": 1, "name": "Maple Property Management"}, "role": null, "permissions": [], "communities": [], "homes": [{"residency_id": 12, "community": {"id": 1, "name": "Harbour Towers"}, "unit": {"id": 1, "label": "North Tower · 101"}, "type": "owner", "is_primary": true, "moved_in_on": "2026-03-25"}]}}
     */
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing(['company', 'resident']);

        $homes = $user->resident === null ? collect() : $user->resident->residencies()->active()->with(['unit.building', 'community'])->get();

        return response()->json(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'company' => $user->company === null ? null : ['id' => $user->company->id, 'name' => $user->company->name],
            'role' => $user->companyRoleName(),
            'permissions' => array_values(array_map(
                fn (Permission $permission) => $permission->value,
                array_filter(Permission::cases(), fn (Permission $permission) => $user->hasCompanyPermission($permission)),
            )),
            'communities' => Community::query()->accessibleBy($user)->orderBy('name')->get(['id', 'name'])
                ->map(fn (Community $community) => ['id' => $community->id, 'name' => $community->name])->values(),
            'homes' => $homes->map(fn (Residency $residency) => [
                'residency_id' => $residency->id,
                'community' => ['id' => $residency->community->id, 'name' => $residency->community->name],
                'unit' => ['id' => $residency->unit->id, 'label' => $residency->unit->label()],
                'type' => $residency->type->value,
                'is_primary' => $residency->is_primary,
                'moved_in_on' => $residency->moved_in_on?->toDateString(),
            ])->values(),
        ]]);
    }
}

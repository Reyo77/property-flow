<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AnnouncementResource;
use App\Models\Announcement;
use App\Models\Community;
use App\Models\User;
use App\Support\Api\ApiQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Communication
 */
class AnnouncementController extends Controller
{
    /**
     * List announcements
     *
     * Residents see the published announcements meant for them (their building, unit or
     * residency type); the team also sees drafts and scheduled ones if they can write them.
     * Pinned ones come first.
     *
     * @apiResourceCollection App\Http\Resources\Api\V1\AnnouncementResource
     *
     * @apiResourceModel App\Models\Announcement paginate=25 with=createdBy
     */
    public function index(Request $request, Community $community): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Announcement::class, $community]);

        /** @var User $user */
        $user = $request->user();

        return AnnouncementResource::collection(ApiQuery::paginate(
            $request,
            $community->announcements()->with(['createdBy', 'buildings', 'units'])->orderByDesc('pinned')->getQuery(),
            sorts: ['published_at'],
            defaultSort: '-published_at',
            visible: fn (Announcement $announcement) => $user->can('view', $announcement),
        ));
    }

    /**
     * Show an announcement
     *
     * @apiResource App\Http\Resources\Api\V1\AnnouncementResource
     *
     * @apiResourceModel App\Models\Announcement with=createdBy
     */
    public function show(Community $community, Announcement $announcement): AnnouncementResource
    {
        Gate::authorize('view', $announcement);

        return new AnnouncementResource($announcement->load('createdBy'));
    }
}

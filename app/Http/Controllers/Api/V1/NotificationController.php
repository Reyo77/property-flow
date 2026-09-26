<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\NotificationResource;
use App\Models\User;
use App\Support\Api\ApiQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Notifications\DatabaseNotification;

/**
 * @group Notifications
 *
 * Your in-app notifications. `type` says what happened (`package-arrived`,
 * `service-request-status-changed`, `announcement-published`, `invoice-overdue`,
 * `amenity-booking-status-changed`, `violation-notice-issued`, `architectural-request-decided`);
 * `payload` holds the ids to open.
 */
class NotificationController extends Controller
{
    /**
     * List notifications
     *
     * @queryParam filter[unread] `1` for unread only. Example: 1
     *
     * @response {"data": [{"id": "9b2f6c1e-2a4d-4c55-8e0f-3d7a1c2b4e51", "type": "package-arrived", "payload": {"package_id": 31, "community_id": 1, "carrier": "Canada Post"}, "read_at": null, "created_at": "2026-10-02T14:05:00+00:00"}], "links": {"first": "https://propertyflow.test/api/v1/notifications?page=1", "last": "https://propertyflow.test/api/v1/notifications?page=1", "prev": null, "next": null}, "meta": {"current_page": 1, "last_page": 1, "per_page": 25, "total": 1}}
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return NotificationResource::collection(ApiQuery::paginate(
            $request,
            DatabaseNotification::query()->whereMorphedTo('notifiable', $user),
            filters: ['unread' => fn ($query, string $value) => $value === '1' ? $query->whereNull('read_at') : null],
            sorts: ['created_at'],
            defaultSort: '-created_at',
        ));
    }

    /**
     * Mark as read
     *
     * @response {"data": {"id": "9b2f6c1e-2a4d-4c55-8e0f-3d7a1c2b4e51", "type": "package-arrived", "payload": {"package_id": 31, "community_id": 1, "carrier": "Canada Post"}, "read_at": "2026-10-02T14:10:00+00:00", "created_at": "2026-10-02T14:05:00+00:00"}}
     */
    public function update(Request $request, string $notification): NotificationResource
    {
        /** @var User $user */
        $user = $request->user();
        $model = $user->notifications()->whereKey($notification)->firstOrFail();
        $model->markAsRead();

        return new NotificationResource($model);
    }
}

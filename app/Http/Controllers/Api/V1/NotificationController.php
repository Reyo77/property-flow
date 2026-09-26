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
     * @apiResourceCollection App\Http\Resources\Api\V1\NotificationResource
     *
     * @apiResourceModel Illuminate\Notifications\DatabaseNotification paginate=25
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
     * @apiResource App\Http\Resources\Api\V1\NotificationResource
     *
     * @apiResourceModel Illuminate\Notifications\DatabaseNotification
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

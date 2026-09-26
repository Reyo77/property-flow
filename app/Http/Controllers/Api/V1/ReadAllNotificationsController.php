<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * @group Notifications
 */
class ReadAllNotificationsController extends Controller
{
    /**
     * Mark all as read
     *
     * @response 204 scenario="Done"
     */
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $user->unreadNotifications()->update(['read_at' => now()]);

        return response()->noContent();
    }
}

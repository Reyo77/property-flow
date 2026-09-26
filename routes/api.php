<?php

use App\Http\Controllers\Api\V1;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Middleware\PrepareApiRequest;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| REST API, version 1
|--------------------------------------------------------------------------
|
| Everything a mobile client needs, under /api/v1. Authenticate with a bearer token from
| POST /api/v1/auth/token. Records belong to a community and are nested under it; another
| company's community or record is always a 404.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('auth/token', [V1\AccessTokenController::class, 'store'])
        ->middleware('throttle:api-token')
        ->name('auth.token.store');

    Route::middleware(['auth:sanctum', PrepareApiRequest::class, 'throttle:api'])->group(function () {
        Route::get('me', V1\MeController::class)->name('me');
        Route::get('auth/tokens', [V1\AccessTokenController::class, 'index'])->name('auth.tokens.index');
        Route::delete('auth/tokens/{token}', [V1\AccessTokenController::class, 'destroy'])->name('auth.tokens.destroy');

        Route::get('notifications', [V1\NotificationController::class, 'index'])->name('notifications.index');
        Route::patch('notifications/{notification}', [V1\NotificationController::class, 'update'])->name('notifications.update');
        Route::post('notifications/read-all', V1\ReadAllNotificationsController::class)->name('notifications.read-all');

        Route::get('my-work-orders', [V1\MyWorkOrderController::class, 'index'])->name('my-work-orders.index');

        Route::get('communities', [V1\CommunityController::class, 'index'])->name('communities.index');
        Route::get('communities/{community}', [V1\CommunityController::class, 'show'])->name('communities.show');

        Route::prefix('communities/{community}')->name('communities.')->scopeBindings()->group(function () {
            Route::get('buildings', [V1\BuildingController::class, 'index'])->name('buildings.index');
            Route::apiResource('units', V1\UnitController::class)->only(['index', 'show']);
            Route::apiResource('residents', V1\ResidentController::class)->only(['index', 'show']);

            Route::apiResource('announcements', V1\AnnouncementController::class)->only(['index', 'show']);
            Route::apiResource('events', V1\EventController::class)->only(['index', 'show']);
            Route::put('events/{event}/rsvp', [V1\EventRsvpController::class, 'update'])->name('events.rsvp.update');

            Route::get('document-folders', [V1\DocumentFolderController::class, 'index'])->name('document-folders.index');
            Route::apiResource('documents', V1\DocumentController::class)->only(['index', 'show']);
            Route::get('documents/{document}/download', DocumentDownloadController::class)->name('documents.download');

            Route::apiResource('service-requests', V1\ServiceRequestController::class)->only(['index', 'store', 'show', 'update'])->parameters(['service-requests' => 'serviceRequest']);
            Route::post('service-requests/{serviceRequest}/comments', [V1\ServiceRequestCommentController::class, 'store'])->name('service-requests.comments.store');
            Route::apiResource('work-orders', V1\WorkOrderController::class)->only(['index', 'show', 'update'])->parameters(['work-orders' => 'workOrder']);
        });
    });
});

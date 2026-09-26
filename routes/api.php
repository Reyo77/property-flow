<?php

use App\Http\Controllers\Api\V1;
use App\Http\Controllers\ArchitecturalDecisionLetterController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\PaymentReceiptController;
use App\Http\Controllers\UnitStatementController;
use App\Http\Controllers\ViolationNoticeLetterController;
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

            Route::apiResource('amenities', V1\AmenityController::class)->only(['index', 'show']);
            Route::apiResource('amenity-bookings', V1\AmenityBookingController::class)->only(['index', 'store', 'show'])->parameters(['amenity-bookings' => 'amenityBooking']);
            Route::post('amenity-bookings/{amenityBooking}/cancellation', [V1\AmenityBookingCancellationController::class, 'store'])->name('amenity-bookings.cancellation.store');
            Route::post('amenity-bookings/{amenityBooking}/decision', [V1\AmenityBookingDecisionController::class, 'store'])->name('amenity-bookings.decision.store');

            Route::apiResource('packages', V1\PackageController::class)->only(['index', 'store', 'show']);
            Route::post('packages/{package}/release', [V1\PackageReleaseController::class, 'store'])->name('packages.release.store');
            Route::apiResource('visitors', V1\VisitorController::class)->only(['index', 'store', 'update']);
            Route::apiResource('guest-passes', V1\GuestPassController::class)->only(['index', 'store', 'show'])->parameters(['guest-passes' => 'guestPass']);
            Route::post('guest-pass-redemptions', [V1\GuestPassRedemptionController::class, 'store'])->name('guest-pass-redemptions.store');
            Route::apiResource('parking-permits', V1\ParkingPermitController::class)->only(['index', 'store', 'destroy'])->parameters(['parking-permits' => 'parkingPermit']);
            Route::apiResource('incident-reports', V1\IncidentReportController::class)->only(['index', 'store', 'show'])->parameters(['incident-reports' => 'incidentReport']);

            Route::get('units/{unit}/account', [V1\UnitAccountController::class, 'show'])->name('units.account.show');
            Route::get('units/{unit}/statement', [V1\UnitStatementController::class, 'index'])->name('units.statement.index');
            Route::get('units/{unit}/statement.pdf', UnitStatementController::class)->name('units.statement-pdf');
            Route::post('units/{unit}/online-payments', [V1\OnlinePaymentController::class, 'store'])->name('units.online-payments.store');
            Route::apiResource('invoices', V1\InvoiceController::class)->only(['index', 'show']);
            Route::apiResource('payments', V1\PaymentController::class)->only(['index', 'store', 'show']);
            Route::get('payments/{payment}/receipt', PaymentReceiptController::class)->name('payments.receipt');
            Route::apiResource('vendor-bills', V1\VendorBillController::class)->only(['index', 'show'])->parameters(['vendor-bills' => 'vendorBill']);
            Route::post('vendor-bills/{vendorBill}/decision', [V1\VendorBillDecisionController::class, 'store'])->name('vendor-bills.decision.store');

            Route::apiResource('ballots', V1\BallotController::class)->only(['index', 'show']);
            Route::post('ballots/{ballot}/votes', [V1\BallotVoteController::class, 'store'])->name('ballots.votes.store');
            Route::post('ballots/{ballot}/proxies', [V1\BallotProxyController::class, 'store'])->name('ballots.proxies.store');
            Route::delete('ballots/{ballot}/proxies/{proxy}', [V1\BallotProxyController::class, 'destroy'])->name('ballots.proxies.destroy');
            Route::apiResource('meetings', V1\MeetingController::class)->only(['index', 'show']);

            Route::apiResource('violations', V1\ViolationController::class)->only(['index', 'store', 'show']);
            Route::get('violations/{violation}/notices/{notice}/letter', ViolationNoticeLetterController::class)->name('violations.notices.letter');
            Route::apiResource('renovation-requests', V1\ArchitecturalRequestController::class)->only(['index', 'store', 'show'])->names('architectural-requests')->parameters(['renovation-requests' => 'architecturalRequest']);
            Route::post('renovation-requests/{architecturalRequest}/decision', [V1\ArchitecturalRequestDecisionController::class, 'store'])->name('architectural-requests.decision.store');
            Route::get('renovation-requests/{architecturalRequest}/decision-letter', ArchitecturalDecisionLetterController::class)->name('architectural-requests.letter');

            Route::apiResource('surveys', V1\SurveyController::class)->only(['index', 'show']);
            Route::post('surveys/{survey}/responses', [V1\SurveyResponseController::class, 'store'])->name('surveys.responses.store');
            Route::apiResource('forms', V1\ConsentFormController::class)->only(['index', 'show'])->names('consent-forms')->parameters(['forms' => 'consentForm']);
            Route::post('forms/{consentForm}/signatures', [V1\ConsentSignatureController::class, 'store'])->name('consent-forms.signatures.store');
            Route::apiResource('board-posts', V1\ForumTopicController::class)->only(['index', 'store', 'show'])->names('forum-topics')->parameters(['board-posts' => 'forumTopic']);
            Route::post('board-posts/{forumTopic}/replies', [V1\ForumPostController::class, 'store'])->name('forum-topics.replies.store');
            Route::post('board-posts/{forumTopic}/reports', [V1\ContentReportController::class, 'store'])->name('forum-topics.reports.store');
        });
    });
});

<?php

use App\Http\Controllers\ArchitecturalDecisionLetterController;
use App\Http\Controllers\AttachmentDownloadController;
use App\Http\Controllers\ConsentSignatureImageController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\DocumentVersionDownloadController;
use App\Http\Controllers\FinancialReportExportController;
use App\Http\Controllers\OnlinePaymentReturnController;
use App\Http\Controllers\PackageSignatureController;
use App\Http\Controllers\PatrolCheckpointQrController;
use App\Http\Controllers\PatrolScanController;
use App\Http\Controllers\PaymentReceiptController;
use App\Http\Controllers\StartOnlinePaymentController;
use App\Http\Controllers\TestCheckoutController;
use App\Http\Controllers\UnitStatementController;
use App\Http\Controllers\ViolationNoticeLetterController;
use App\Http\Middleware\RememberCurrentCommunity;
use App\Livewire\AccessKeys;
use App\Livewire\Amenities;
use App\Livewire\Announcements;
use App\Livewire\ArchitecturalRequests;
use App\Livewire\Assets;
use App\Livewire\Buildings;
use App\Livewire\Communities;
use App\Livewire\Dashboard;
use App\Livewire\Documents;
use App\Livewire\Engagement;
use App\Livewire\EntryAuthorizations;
use App\Livewire\Events;
use App\Livewire\Finance;
use App\Livewire\FrontDesk;
use App\Livewire\Governance;
use App\Livewire\GuestPasses;
use App\Livewire\IncidentReports;
use App\Livewire\Invitations;
use App\Livewire\Notifications;
use App\Livewire\Packages;
use App\Livewire\ParkingPermits;
use App\Livewire\PatrolRoutes;
use App\Livewire\PhoneBook;
use App\Livewire\Residents;
use App\Livewire\ServiceRequests;
use App\Livewire\ShiftLog;
use App\Livewire\Tasks;
use App\Livewire\Team;
use App\Livewire\Units;
use App\Livewire\Vendors;
use App\Livewire\Violations;
use App\Livewire\Visitors;
use App\Livewire\WorkOrders;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::livewire('invitations/{token}', Invitations\Accept::class)
    ->middleware(['guest', 'throttle:30,1'])
    ->name('invitations.accept');

Route::middleware('auth')->group(function () {
    Route::livewire('dashboard', Dashboard::class)->name('dashboard');

    Route::livewire('notifications', Notifications\Index::class)->name('notifications.index');

    Route::livewire('team', Team\Index::class)->name('team.index');
    Route::livewire('team/roles', Team\Roles::class)->name('team.roles');
    Route::livewire('vendors', Vendors\Index::class)->name('vendors.index');
    Route::livewire('my-work-orders', WorkOrders\MyWorkOrders::class)->name('work-orders.mine');
    Route::get('patrol-scan/{qrToken}', PatrolScanController::class)->name('patrol-scan');
    Route::get('payments/test-checkout/{reference}', [TestCheckoutController::class, 'show'])->name('payments.test-checkout');
    Route::post('payments/test-checkout/{reference}', [TestCheckoutController::class, 'update'])->name('payments.test-checkout.complete');

    Route::livewire('communities', Communities\Index::class)->name('communities.index');
    Route::livewire('communities/create', Communities\Create::class)->name('communities.create');

    Route::prefix('communities/{community}')
        ->name('communities.')
        ->middleware(RememberCurrentCommunity::class)
        ->scopeBindings()
        ->group(function () {
            Route::livewire('/', Communities\Show::class)->name('show');
            Route::livewire('edit', Communities\Edit::class)->name('edit');
            Route::livewire('buildings', Buildings\Index::class)->name('buildings.index');
            Route::livewire('units', Units\Index::class)->name('units.index');
            Route::livewire('units/import', Units\Import::class)->name('units.import');
            Route::livewire('units/{unit}', Units\Show::class)->name('units.show');
            Route::livewire('residents', Residents\Index::class)->name('residents.index');
            Route::livewire('residents/{resident}', Residents\Show::class)->name('residents.show');
            Route::livewire('phone-book', PhoneBook\Index::class)->name('phone-book.index');
            Route::livewire('events', Events\Index::class)->name('events.index');
            Route::livewire('documents', Documents\Index::class)->name('documents.index');
            Route::get('documents/{document}/download', DocumentDownloadController::class)->name('documents.download');
            Route::get('documents/{document}/versions/{version}/download', DocumentVersionDownloadController::class)->name('documents.versions.download');
            Route::livewire('announcements', Announcements\Index::class)->name('announcements.index');
            Route::livewire('service-requests', ServiceRequests\Index::class)->name('service-requests.index');
            Route::livewire('service-requests/create', ServiceRequests\Create::class)->name('service-requests.create');
            Route::livewire('service-requests/{serviceRequest}', ServiceRequests\Show::class)->name('service-requests.show');
            Route::get('attachments/{attachment}/download', AttachmentDownloadController::class)->name('attachments.download');
            Route::livewire('tasks', Tasks\Index::class)->name('tasks.index');
            Route::livewire('assets', Assets\Index::class)->name('assets.index');
            Route::livewire('assets/{asset}', Assets\Show::class)->name('assets.show');
            Route::livewire('amenities', Amenities\Index::class)->name('amenities.index');
            Route::livewire('amenities/{amenity}', Amenities\Show::class)->name('amenities.show');
            Route::livewire('packages', Packages\Index::class)->name('packages.index');
            Route::get('packages/{package}/signature', PackageSignatureController::class)->name('packages.signature');
            Route::livewire('visitors', Visitors\Index::class)->name('visitors.index');
            Route::livewire('guest-passes', GuestPasses\Index::class)->name('guest-passes.index');
            Route::livewire('parking-permits', ParkingPermits\Index::class)->name('parking-permits.index');
            Route::livewire('incident-reports', IncidentReports\Index::class)->name('incident-reports.index');
            Route::livewire('incident-reports/{incidentReport}', IncidentReports\Show::class)->name('incident-reports.show');
            Route::livewire('keys', AccessKeys\Index::class)->name('access-keys.index');
            Route::livewire('entry-authorizations', EntryAuthorizations\Index::class)->name('entry-authorizations.index');
            Route::livewire('patrol-routes', PatrolRoutes\Index::class)->name('patrol-routes.index');
            Route::livewire('patrol-routes/{patrolRoute}', PatrolRoutes\Show::class)->name('patrol-routes.show');
            Route::get('patrol-checkpoints/{patrolCheckpoint}/qr', PatrolCheckpointQrController::class)->name('patrol-checkpoints.qr');
            Route::livewire('shift-log', ShiftLog\Index::class)->name('shift-log.index');
            Route::livewire('front-desk', FrontDesk\Mode::class)->name('front-desk.mode');
            Route::livewire('ballots', Governance\Ballots::class)->name('ballots.index');
            Route::livewire('ballots/create', Governance\BallotForm::class)->name('ballots.create');
            Route::livewire('ballots/{ballot}', Governance\BallotShow::class)->name('ballots.show');
            Route::livewire('ballots/{ballot}/edit', Governance\BallotForm::class)->name('ballots.edit');
            Route::livewire('meetings', Governance\Meetings::class)->name('meetings.index');
            Route::livewire('board', Governance\BoardPortal::class)->name('board');
            Route::livewire('surveys', Engagement\Surveys::class)->name('surveys.index');
            Route::livewire('surveys/create', Engagement\SurveyForm::class)->name('surveys.create');
            Route::livewire('surveys/{survey}', Engagement\SurveyShow::class)->name('surveys.show');
            Route::livewire('forms', Engagement\ConsentForms::class)->name('consent-forms.index');
            Route::livewire('forms/{consentForm}', Engagement\ConsentFormShow::class)->name('consent-forms.show');
            Route::get('forms/{consentForm}/signatures/{signature}', ConsentSignatureImageController::class)->name('consent-forms.signature');
            Route::livewire('board-posts', Engagement\Forum::class)->name('forum.index');
            Route::livewire('board-posts/{forumTopic}', Engagement\ForumTopicShow::class)->name('forum.show');
            Route::livewire('violations', Violations\Index::class)->name('violations.index');
            Route::livewire('violations/{violation}', Violations\Show::class)->name('violations.show');
            Route::get('violations/{violation}/notices/{notice}/letter', ViolationNoticeLetterController::class)->name('violations.notices.letter');
            Route::livewire('renovation-requests', ArchitecturalRequests\Index::class)->name('architectural-requests.index');
            Route::livewire('renovation-requests/{architecturalRequest}', ArchitecturalRequests\Show::class)->name('architectural-requests.show');
            Route::get('renovation-requests/{architecturalRequest}/decision-letter', ArchitecturalDecisionLetterController::class)->name('architectural-requests.letter');
            Route::livewire('meetings/{meeting}', Governance\MeetingShow::class)->name('meetings.show');
            Route::livewire('finance', Finance\Overview::class)->name('finance.overview');
            Route::livewire('finance/invoices', Finance\Invoices::class)->name('finance.invoices');
            Route::livewire('finance/payments', Finance\Payments::class)->name('finance.payments');
            Route::livewire('finance/setup', Finance\Setup::class)->name('finance.setup');
            Route::livewire('finance/billing', Finance\Billing::class)->name('finance.billing');
            Route::livewire('finance/bills', Finance\Bills::class)->name('finance.bills');
            Route::livewire('finance/budget', Finance\Budgets::class)->name('finance.budget');
            Route::livewire('finance/reports', Finance\Reports::class)->name('finance.reports');
            Route::get('finance/reports/export', FinancialReportExportController::class)->name('finance.reports.export');
            Route::livewire('finance/reconciliation', Finance\Reconciliation::class)->name('finance.reconciliation');
            Route::livewire('finance/reconciliation/{bankStatement}', Finance\BankStatementShow::class)->name('finance.reconciliation.show');
            Route::get('payments/{payment}/receipt', PaymentReceiptController::class)->name('payments.receipt');
            Route::livewire('units/{unit}/account', Finance\UnitAccount::class)->name('units.account');
            Route::get('units/{unit}/statement', UnitStatementController::class)->name('units.statement');
            Route::post('units/{unit}/pay-online', StartOnlinePaymentController::class)->name('units.pay-online');
            Route::get('units/{unit}/payment-return', OnlinePaymentReturnController::class)->name('units.payment-return');
        });
});

require __DIR__.'/settings.php';

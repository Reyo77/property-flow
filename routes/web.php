<?php

use App\Http\Controllers\AttachmentDownloadController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\DocumentVersionDownloadController;
use App\Http\Middleware\RememberCurrentCommunity;
use App\Livewire\Amenities;
use App\Livewire\Announcements;
use App\Livewire\Assets;
use App\Livewire\Buildings;
use App\Livewire\Communities;
use App\Livewire\Dashboard;
use App\Livewire\Documents;
use App\Livewire\Events;
use App\Livewire\Invitations;
use App\Livewire\Notifications;
use App\Livewire\PhoneBook;
use App\Livewire\Residents;
use App\Livewire\ServiceRequests;
use App\Livewire\Tasks;
use App\Livewire\Team;
use App\Livewire\Units;
use App\Livewire\Vendors;
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
        });
});

require __DIR__.'/settings.php';

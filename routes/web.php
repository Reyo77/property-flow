<?php

use App\Http\Middleware\RememberCurrentCommunity;
use App\Livewire\Buildings;
use App\Livewire\Communities;
use App\Livewire\Dashboard;
use App\Livewire\Units;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware('auth')->group(function () {
    Route::livewire('dashboard', Dashboard::class)->name('dashboard');

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
        });
});

require __DIR__.'/settings.php';

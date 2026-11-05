<?php

use App\Enums\Ability;
use App\Http\Controllers\Admin\NoteController;
use App\Http\Controllers\Admin\ParentshipController;
use App\Http\Controllers\Admin\PersonController;
use App\Http\Controllers\Admin\PersonSearchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PortalController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

if (app()->isLocal()) {
    Route::inertia('komponen', 'komponen')->name('komponen');
}

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('portal', PortalController::class)->name('portal');

    Route::middleware('can:'.Ability::AdminAccess->value)
        ->prefix('admin')
        ->name('admin.')
        ->group(function () {
            Route::get('people/search', PersonSearchController::class)->name('people.search');
            Route::resource('people', PersonController::class)->except('destroy');

            Route::resource('people.parentships', ParentshipController::class)
                ->only(['store', 'update', 'destroy'])
                ->scoped();

            Route::resource('notes', NoteController::class)->only(['store', 'update', 'destroy']);
        });
});

require __DIR__.'/settings.php';

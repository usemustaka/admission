<?php

use App\Enums\Ability;
use App\Http\Controllers\Admin\Admission\RulesetContentController;
use App\Http\Controllers\Admin\Admission\RulesetController;
use App\Http\Controllers\Admin\Admission\RulesetOrderController;
use App\Http\Controllers\Admin\Admission\WaveController;
use App\Http\Controllers\Admin\Finance\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\NoteController;
use App\Http\Controllers\Admin\ParentshipController;
use App\Http\Controllers\Admin\PersonController;
use App\Http\Controllers\Admin\PersonSearchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PaymentProofController;
use App\Http\Controllers\Portal\BillController;
use App\Http\Controllers\Portal\ChildController;
use App\Http\Controllers\Portal\ChildDataController;
use App\Http\Controllers\Portal\PaymentController;
use App\Http\Controllers\PortalController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

if (app()->isLocal()) {
    Route::inertia('komponen', 'komponen')->name('komponen');
}

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('portal', PortalController::class)->name('portal');

    Route::prefix('portal/anak')->name('portal.children.')->group(function () {
        Route::get('daftar', [ChildController::class, 'create'])->name('create');
        Route::post('/', [ChildController::class, 'store'])->name('store');
        Route::get('{registrant}', [ChildController::class, 'show'])->name('show');
        Route::get('{registrant}/data', [ChildDataController::class, 'edit'])->name('data.edit');
        Route::post('{registrant}/data', [ChildDataController::class, 'update'])->name('data.update');
        Route::get('{registrant}/tagihan', [BillController::class, 'index'])->name('bills');
    });

    Route::get('portal/tagihan', [BillController::class, 'index'])->name('portal.bills.index');
    Route::get('portal/pembayaran/baru', [PaymentController::class, 'create'])->name('portal.payments.create');
    Route::post('portal/pembayaran', [PaymentController::class, 'store'])->name('portal.payments.store');

    Route::get('payments/{payment}/bukti', PaymentProofController::class)->name('payments.proof');

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

            Route::get('payments/open-items', [AdminPaymentController::class, 'openItems'])->name('payments.open-items');
            Route::resource('payments', AdminPaymentController::class)->only(['index', 'create', 'store', 'show']);
            Route::put('payments/{payment}/verify', [AdminPaymentController::class, 'verify'])->name('payments.verify');
            Route::put('payments/{payment}/reject', [AdminPaymentController::class, 'reject'])->name('payments.reject');

            Route::resource('waves', WaveController::class)->only(['index', 'store', 'update']);

            Route::resource('rulesets', RulesetController::class);
            Route::patch('rulesets/{ruleset}/move', RulesetOrderController::class)->name('rulesets.move');
            Route::put('rulesets/{ruleset}/bills', [RulesetContentController::class, 'bills'])->name('rulesets.bills');
            Route::put('rulesets/{ruleset}/exam-aspects', [RulesetContentController::class, 'examAspects'])->name('rulesets.exam-aspects');
            Route::put('rulesets/{ruleset}/exam-schedule', [RulesetContentController::class, 'examSchedule'])->name('rulesets.exam-schedule');
        });
});

require __DIR__.'/settings.php';

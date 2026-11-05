<?php

use App\Enums\Ability;
use App\Enums\SettingGroup;
use App\Http\Controllers\Admin\Admission\AdmissionSettingController;
use App\Http\Controllers\Admin\Admission\ExamController;
use App\Http\Controllers\Admin\Admission\ExamScheduleController;
use App\Http\Controllers\Admin\Admission\ExamScoreGridController;
use App\Http\Controllers\Admin\Admission\RegistrantBulkController;
use App\Http\Controllers\Admin\Admission\RegistrantController;
use App\Http\Controllers\Admin\Admission\RegistrantExportController;
use App\Http\Controllers\Admin\Admission\RegistrantQuickViewController;
use App\Http\Controllers\Admin\Admission\RegistrantWorkflowController;
use App\Http\Controllers\Admin\Admission\RulesetContentController;
use App\Http\Controllers\Admin\Admission\RulesetController;
use App\Http\Controllers\Admin\Admission\RulesetOrderController;
use App\Http\Controllers\Admin\Admission\WaveController;
use App\Http\Controllers\Admin\Finance\ArrearsController;
use App\Http\Controllers\Admin\Finance\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\Finance\PaymentCorrectionController;
use App\Http\Controllers\Admin\Finance\PaymentReconciliationController;
use App\Http\Controllers\Admin\NoteController;
use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Controllers\Admin\ParentshipController;
use App\Http\Controllers\Admin\PeriodController;
use App\Http\Controllers\Admin\PersonController;
use App\Http\Controllers\Admin\PersonQuickViewController;
use App\Http\Controllers\Admin\PersonSearchController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\YearController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PaymentProofController;
use App\Http\Controllers\PaymentReceiptController;
use App\Http\Controllers\Portal\BillController;
use App\Http\Controllers\Portal\ChildController;
use App\Http\Controllers\Portal\ChildDataController;
use App\Http\Controllers\Portal\ChildExamController;
use App\Http\Controllers\Portal\ChildSubmissionController;
use App\Http\Controllers\Portal\PaymentController;
use App\Http\Controllers\PortalController;
use App\Http\Middleware\RequireStaffTwoFactor;
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
        Route::get('{registrant}/ujian', [ChildExamController::class, 'edit'])->name('exam.edit');
        Route::post('{registrant}/ujian', [ChildExamController::class, 'update'])->name('exam.update');
        Route::post('{registrant}/kirim', [ChildSubmissionController::class, 'store'])->name('submit');
        Route::get('{registrant}/tagihan', [BillController::class, 'index'])->name('bills');
    });

    Route::get('portal/tagihan', [BillController::class, 'index'])->name('portal.bills.index');
    Route::get('portal/pembayaran/baru', [PaymentController::class, 'create'])->name('portal.payments.create');
    Route::post('portal/pembayaran', [PaymentController::class, 'store'])->name('portal.payments.store');

    Route::get('payments/{payment}/bukti', PaymentProofController::class)->name('payments.proof');
    Route::get('payments/{payment}/kwitansi', PaymentReceiptController::class)->name('payments.receipt');

    Route::middleware(['can:'.Ability::AdminAccess->value, RequireStaffTwoFactor::class])
        ->prefix('admin')
        ->name('admin.')
        ->group(function () {
            Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
            Route::get('settings/{group}', [SettingController::class, 'edit'])->whereIn('group', array_column(SettingGroup::cases(), 'value'))->name('settings.edit');
            Route::post('settings/{group}', [SettingController::class, 'update'])->whereIn('group', array_column(SettingGroup::cases(), 'value'))->name('settings.update');

            Route::get('people/search', PersonSearchController::class)->name('people.search');
            Route::get('people/{person}/ringkasan', PersonQuickViewController::class)->name('people.quick-view');
            Route::resource('people', PersonController::class)->except('destroy');

            Route::resource('people.parentships', ParentshipController::class)
                ->only(['store', 'update', 'destroy'])
                ->scoped();

            Route::resource('notes', NoteController::class)->only(['store', 'update', 'destroy']);

            Route::get('registrants', [RegistrantController::class, 'index'])->name('registrants.index');
            Route::get('registrants/export', RegistrantExportController::class)->name('registrants.export');
            Route::post('registrants/bulk', RegistrantBulkController::class)->name('registrants.bulk');
            Route::post('registrants/bulk/pratinjau', [RegistrantBulkController::class, 'preview'])->name('registrants.bulk-preview');
            Route::get('registrants/{registrant}', [RegistrantController::class, 'show'])->name('registrants.show');
            Route::get('registrants/{registrant}/ringkasan', RegistrantQuickViewController::class)->name('registrants.quick-view');
            Route::get('registrants/{registrant}/data', [RegistrantController::class, 'edit'])->name('registrants.data.edit');
            Route::post('registrants/{registrant}/data', [RegistrantController::class, 'update'])->name('registrants.data.update');

            Route::prefix('registrants/{registrant}')->name('registrants.')->controller(RegistrantWorkflowController::class)->group(function () {
                Route::post('accept', 'accept')->name('accept');
                Route::post('revise', 'revise')->name('revise');
                Route::post('bypass', 'bypass')->name('bypass');
                Route::post('announce', 'announce')->name('announce');
                Route::post('place', 'place')->name('place');
                Route::post('resolve-identity', 'resolveIdentity')->name('resolve-identity');
                Route::post('repoint-identity', 'repointIdentity')->name('repoint-identity');
                Route::put('exam-session', 'examSession')->name('exam-session');
                Route::put('scores', 'scores')->name('scores');
            });

            Route::get('exams', [ExamController::class, 'index'])->name('exams.index');
            Route::get('exams/jadwal', [ExamScheduleController::class, 'edit'])->name('exams.schedule.edit');
            Route::put('exams/jadwal', [ExamScheduleController::class, 'update'])->name('exams.schedule.update');
            Route::get('exams/sessions/{session}', [ExamController::class, 'show'])->name('exams.sessions.show');
            Route::get('exams/sessions/{session}/ekspor', [ExamController::class, 'export'])->name('exams.sessions.export');
            Route::get('exams/sessions/{session}/nilai', [ExamScoreGridController::class, 'edit'])->name('exams.sessions.scores.edit');
            Route::put('exams/sessions/{session}/nilai', [ExamScoreGridController::class, 'update'])->name('exams.sessions.scores.update');

            Route::get('admission/settings', [AdmissionSettingController::class, 'edit'])->name('admission-settings.edit');
            Route::put('admission/settings', [AdmissionSettingController::class, 'update'])->name('admission-settings.update');

            Route::get('payments/open-items', [AdminPaymentController::class, 'openItems'])->name('payments.open-items');
            Route::get('payments/ekspor', [AdminPaymentController::class, 'export'])->name('payments.export');
            Route::get('payments/tunggakan', [ArrearsController::class, 'index'])->name('payments.arrears');
            Route::get('payments/tunggakan/ekspor', [ArrearsController::class, 'export'])->name('payments.arrears.export');
            Route::get('payments/rekonsiliasi', [PaymentReconciliationController::class, 'index'])->name('payments.reconciliation');
            Route::get('payments/rekonsiliasi/ekspor', [PaymentReconciliationController::class, 'export'])->name('payments.reconciliation.export');
            Route::post('payments/verifikasi-massal', [AdminPaymentController::class, 'bulkVerify'])->name('payments.bulk-verify');
            Route::get('payments/{payment}/ringkasan', [AdminPaymentController::class, 'quickView'])->name('payments.quick-view');
            Route::resource('payments', AdminPaymentController::class)->only(['index', 'create', 'store', 'show']);
            Route::put('payments/{payment}/verify', [AdminPaymentController::class, 'verify'])->name('payments.verify');
            Route::put('payments/{payment}/reject', [AdminPaymentController::class, 'reject'])->name('payments.reject');
            Route::put('payments/{payment}/void', [PaymentCorrectionController::class, 'void'])->name('payments.void');
            Route::put('payments/{payment}/reopen', [PaymentCorrectionController::class, 'reopen'])->name('payments.reopen');

            Route::resource('users', UserController::class)->only(['index', 'create', 'store', 'show', 'update']);
            Route::prefix('users/{user}')->name('users.')->controller(UserController::class)->group(function () {
                Route::post('deactivate', 'deactivate')->name('deactivate');
                Route::post('reactivate', 'reactivate')->name('reactivate');
                Route::post('password-change', 'requirePasswordChange')->name('password-change');
                Route::post('setup-link', 'sendSetupLink')->name('setup-link');
            });

            Route::resource('roles', RoleController::class)->except('show');
            Route::post('roles/{role}/duplicate', [RoleController::class, 'duplicate'])->name('roles.duplicate');

            Route::resource('waves', WaveController::class)->only(['index', 'store', 'update']);

            Route::resource('years', YearController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::post('years/{year}/activate', [YearController::class, 'activate'])->name('years.activate');
            Route::resource('years.periods', PeriodController::class)->only(['store', 'update', 'destroy'])->scoped();

            Route::resource('organizations', OrganizationController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::patch('organizations/{organization}/move', [OrganizationController::class, 'move'])->name('organizations.move');

            Route::get('students', [StudentController::class, 'index'])->name('students.index');
            Route::put('students/{student}', [StudentController::class, 'update'])->name('students.update');
            Route::post('students/{student}/cancel-placement', [StudentController::class, 'cancelPlacement'])->name('students.cancel-placement');

            Route::resource('rulesets', RulesetController::class);
            Route::patch('rulesets/{ruleset}/move', RulesetOrderController::class)->name('rulesets.move');
            Route::put('rulesets/{ruleset}/bills', [RulesetContentController::class, 'bills'])->name('rulesets.bills');
            Route::put('rulesets/{ruleset}/exam-aspects', [RulesetContentController::class, 'examAspects'])->name('rulesets.exam-aspects');
        });
});

require __DIR__.'/settings.php';

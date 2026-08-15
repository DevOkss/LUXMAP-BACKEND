<?php

use App\Http\Controllers\Admin\AcademicTermController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AdviserController;
use App\Http\Controllers\Admin\CalendarController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeviceBindingController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\FeeController;
use App\Http\Controllers\Admin\HeadController;
use App\Http\Controllers\Admin\InstituteController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\OfficerController;
use App\Http\Controllers\Admin\PaymentAccountController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PaymentSubmissionController;
use App\Http\Controllers\Admin\ProgramController;
use App\Http\Controllers\Admin\QrConfigurationController;
use App\Http\Controllers\Admin\ShiftRequestController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

$adminRoles = 'super_admin,ssc_head,institute_head,sro_head,ssc_officer,isc_officer,sro_officer';

Route::get('/', fn () => redirect()->route('login'))->name('home');

// Public landing page for installing the student PWA (not App Store/Play Store).
Route::get('/app', [\App\Http\Controllers\LandingController::class, 'app'])
    ->name('app.landing');

Route::get('dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified', "role:{$adminRoles}"])->name('dashboard');

Route::middleware(['auth', 'verified', "role:{$adminRoles}"])->name('admin.')->prefix('admin')->group(function () {

    Route::middleware('role:super_admin')->group(function () {
        Route::get('/users', [UserController::class, 'index'])
            ->name('users.index');
        Route::get('/users/{user}', [UserController::class, 'show'])
            ->name('users.show');

        Route::get('/heads', [HeadController::class, 'index'])
            ->name('heads.index');
        Route::get('/heads/create', [HeadController::class, 'create'])
            ->name('heads.create');
        Route::post('/heads', [HeadController::class, 'store'])
            ->name('heads.store');
        Route::get('/heads/{user}', [HeadController::class, 'show'])
            ->name('heads.show');
        Route::get('/heads/{user}/edit', [HeadController::class, 'edit'])
            ->name('heads.edit');
        Route::put('/heads/{user}', [HeadController::class, 'update'])
            ->name('heads.update');
        Route::delete('/heads/{user}', [HeadController::class, 'destroy'])
            ->name('heads.destroy');
    });

    Route::middleware('role:ssc_head,institute_head,sro_head')->group(function () {
        Route::get('/officers', [OfficerController::class, 'index'])
            ->name('officers.index');
        Route::get('/officers/assign', [OfficerController::class, 'create'])
            ->name('officers.create');
        Route::get('/officers/search', [OfficerController::class, 'search'])
            ->name('officers.search');
        Route::post('/officers/assign', [OfficerController::class, 'store'])
            ->name('officers.store');
        Route::delete('/officers', [OfficerController::class, 'destroy'])
            ->name('officers.destroy');
    });

    Route::middleware('role:ssc_head')->group(function () {
        Route::get('/advisers', [AdviserController::class, 'index'])
            ->name('advisers.index');
    });

    Route::middleware('role:institute_head,sro_head')->group(function () {
        Route::get('/students', [StudentController::class, 'index'])
            ->name('students.index');
    });

    Route::middleware('role:super_admin')->group(function () {
        Route::get('/institutes', [InstituteController::class, 'index'])
            ->name('institutes.index');
        Route::get('/institutes/create', [InstituteController::class, 'create'])
            ->name('institutes.create');
        Route::post('/institutes', [InstituteController::class, 'store'])
            ->name('institutes.store');
        Route::get('/institutes/{institute}', [InstituteController::class, 'show'])
            ->name('institutes.show');
        Route::get('/institutes/{institute}/edit', [InstituteController::class, 'edit'])
            ->name('institutes.edit');
        Route::put('/institutes/{institute}', [InstituteController::class, 'update'])
            ->name('institutes.update');
        Route::delete('/institutes/{institute}', [InstituteController::class, 'destroy'])
            ->name('institutes.destroy');

        Route::post('/institutes/{institute}/programs', [ProgramController::class, 'store'])
            ->name('programs.store');
        Route::put('/programs/{program}', [ProgramController::class, 'update'])
            ->name('programs.update');
        Route::delete('/programs/{program}', [ProgramController::class, 'destroy'])
            ->name('programs.destroy');
    });

    // Event management — officers only (heads and super admin are view-only).
    Route::middleware('role:ssc_officer,isc_officer,sro_officer')->group(function () {
        Route::get('/events/create', [EventController::class, 'create'])
            ->name('events.create');
        Route::post('/events', [EventController::class, 'store'])
            ->name('events.store');
        Route::get('/events/{event}/edit', [EventController::class, 'edit'])
            ->name('events.edit');
        Route::put('/events/{event}', [EventController::class, 'update'])
            ->name('events.update');
        Route::delete('/events/{event}', [EventController::class, 'destroy'])
            ->name('events.destroy');
        Route::post('/events/{event}/publish', [EventController::class, 'publish'])
            ->name('events.publish');
        Route::post('/events/{event}/unpublish', [EventController::class, 'unpublish'])
            ->name('events.unpublish');
        Route::post('/events/{event}/complete', [EventController::class, 'complete'])
            ->name('events.complete');
        Route::get('/events/{event}/qr', [EventController::class, 'qr'])
            ->name('events.qr');
        Route::get('/events/{event}/attendance/export', [EventController::class, 'exportAttendance'])
            ->name('events.attendance-export');
        Route::post('/events/{event}/qr-configurations', [QrConfigurationController::class, 'store'])
            ->name('events.qr-configurations.store');
        Route::put('/events/{event}/qr-configurations/{config}', [QrConfigurationController::class, 'update'])
            ->name('events.qr-configurations.update');
        Route::post('/events/{event}/qr-configurations/{config}/generate', [QrConfigurationController::class, 'generate'])
            ->name('events.qr-configurations.generate');
        Route::get('/events/{event}/qr-configurations/{config}/download', [QrConfigurationController::class, 'download'])
            ->name('events.qr-configurations.download');
        Route::delete('/events/{event}/qr-configurations/{config}', [QrConfigurationController::class, 'destroy'])
            ->name('events.qr-configurations.destroy');
    });

    // Events — view (all admin portal roles, including heads view-only).
    Route::get('/events', [EventController::class, 'index'])
        ->name('events.index');
    Route::get('/events/{event}', [EventController::class, 'show'])
        ->name('events.show');

    Route::get('/calendar', [CalendarController::class, 'index'])
        ->name('calendar.index');

    Route::get('/activity-logs', [ActivityLogController::class, 'index'])
        ->name('activity-logs.index');

    // Fees — control (heads only).
    Route::middleware('role:ssc_head,institute_head,sro_head')->group(function () {
        Route::get('/fees/create', [FeeController::class, 'create'])
            ->name('fees.create');
        Route::post('/fees', [FeeController::class, 'store'])
            ->name('fees.store');
        Route::get('/fees/{fee}/edit', [FeeController::class, 'edit'])
            ->name('fees.edit');
        Route::put('/fees/{fee}', [FeeController::class, 'update'])
            ->name('fees.update');
        Route::delete('/fees/{fee}', [FeeController::class, 'destroy'])
            ->name('fees.destroy');
        Route::post('/fees/{fee}/publish', [FeeController::class, 'publish'])
            ->name('fees.publish');
        Route::post('/fees/{fee}/unpublish', [FeeController::class, 'unpublish'])
            ->name('fees.unpublish');
        Route::post('/fees/penalty', [FeeController::class, 'storePenalty'])
            ->name('fees.penalty.store');
    });

    // Shift requests — super admin reviews student shift applications.
    Route::middleware('role:super_admin')->group(function () {
        Route::get('/shift-requests', [ShiftRequestController::class, 'index'])
            ->name('shift-requests.index');
        Route::patch('/shift-requests/{shiftRequest}/approve', [ShiftRequestController::class, 'approve'])
            ->name('shift-requests.approve');
        Route::patch('/shift-requests/{shiftRequest}/reject', [ShiftRequestController::class, 'reject'])
            ->name('shift-requests.reject');
    });

    // Academic terms — super admin manages the current semester/school year.
    Route::middleware('role:super_admin')->group(function () {
        Route::get('/academic-terms', [AcademicTermController::class, 'index'])
            ->name('academic-terms.index');
        Route::post('/academic-terms', [AcademicTermController::class, 'store'])
            ->name('academic-terms.store');
        Route::post('/academic-terms/{term}/activate', [AcademicTermController::class, 'activate'])
            ->name('academic-terms.activate');
    });

    // Fees — view (heads who control; officers view amounts). Super admin: no access.
    Route::middleware('role:ssc_head,institute_head,sro_head,ssc_officer,isc_officer,sro_officer')->group(function () {
        Route::get('/fees', [FeeController::class, 'index'])
            ->name('fees.index');
        Route::get('/fees/{fee}', [FeeController::class, 'show'])
            ->name('fees.show');
    });

    // Payments module — heads monitor; officers record cash, exempt, and verify submissions.
    Route::middleware('role:ssc_head,institute_head,sro_head,ssc_officer,isc_officer,sro_officer')->group(function () {
        Route::get('/payments', [PaymentController::class, 'index'])
            ->name('payments.index');
        Route::get('/payments/export', [PaymentController::class, 'export'])
            ->name('payments.export');
        Route::get('/payments/students/{user}/obligations', [PaymentController::class, 'studentDetail'])
            ->name('payments.students.detail');
        Route::post('/payments/cash', [PaymentController::class, 'recordCash'])
            ->name('payments.cash');
        Route::post('/payments/exempt', [PaymentController::class, 'exempt'])
            ->name('payments.exempt');
        Route::get('/payments/{uuid}', [PaymentController::class, 'show'])
            ->name('payments.show');

        Route::get('/payments/submissions/{groupKey}', [PaymentSubmissionController::class, 'show'])
            ->name('payments.submissions.show');
        Route::post('/payments/submissions/{groupKey}/approve', [PaymentSubmissionController::class, 'approve'])
            ->name('payments.submissions.approve');
        Route::post('/payments/submissions/{groupKey}/reject', [PaymentSubmissionController::class, 'reject'])
            ->name('payments.submissions.reject');
    });

    // Payment accounts — viewable by heads (and officers, to verify cashless
    // submissions against the official account/QR); created/edit/removed only by the head.
    Route::middleware('role:ssc_head,institute_head,sro_head,ssc_officer,isc_officer,sro_officer')->group(function () {
        Route::get('/payment-accounts', [PaymentAccountController::class, 'index'])
            ->name('payment-accounts.index');
    });
    Route::middleware('role:ssc_head,institute_head,sro_head')->group(function () {
        Route::post('/payment-accounts', [PaymentAccountController::class, 'store'])
            ->name('payment-accounts.store');
        Route::delete('/payment-accounts/{account}', [PaymentAccountController::class, 'destroy'])
            ->name('payment-accounts.destroy');
    });

    Route::get('/notifications', [NotificationController::class, 'index'])
        ->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])
        ->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])
        ->name('notifications.read-all');

    // One-device binding — heads and super admin monitor; any of them unbind.
    Route::middleware('role:super_admin,ssc_head,institute_head,sro_head')->group(function () {
        Route::get('/device-bindings', [DeviceBindingController::class, 'index'])
            ->name('device-bindings.index');
        Route::delete('/device-bindings/{binding}', [DeviceBindingController::class, 'unbind'])
            ->name('device-bindings.unbind');
    });

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

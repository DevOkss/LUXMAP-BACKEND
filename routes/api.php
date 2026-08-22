<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\FaceController;
use App\Http\Controllers\Api\FeeController;
use App\Http\Controllers\Api\InstitutionAuthController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\QrConfigurationController;
use App\Http\Controllers\Api\ReceiptController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ShiftRequestController;
use Illuminate\Support\Facades\Route;

Route::name('api.')->group(function () {

    // Public routes
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/institution/auth', [InstitutionAuthController::class, 'authenticate'])
        ->middleware('throttle:10,1')
        ->name('institution.auth');

    // Authenticated routes
    Route::middleware('auth:sanctum')->group(function () {

        // Auth
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/user', [AuthController::class, 'user'])->name('user');
        Route::post('/me/refresh', [AuthController::class, 'refresh'])->name('me.refresh');

        // Onboarding
        Route::get('/onboarding', [OnboardingController::class, 'index'])->name('onboarding.index');
        Route::patch('/onboarding', [OnboardingController::class, 'update'])->name('onboarding.update');

        // Shift Requests
        Route::get('/shift-requests', [ShiftRequestController::class, 'index'])->name('shift-requests.index');
        Route::get('/shift-requests/{shiftRequest}', [ShiftRequestController::class, 'show'])->name('shift-requests.show');
        Route::post('/shift-requests', [ShiftRequestController::class, 'store'])->name('shift-requests.store');

        // Workspace
        Route::get('/workspaces', [AuthController::class, 'workspaces'])->name('workspaces');
        Route::put('/workspace/{organization}', [AuthController::class, 'switchWorkspace'])->name('workspace.switch');

        // Organizations
        Route::apiResource('organizations', OrganizationController::class);

        // Events
        Route::get('/events/upcoming', [EventController::class, 'upcoming'])->name('events.upcoming');
        Route::get('/events/student', [EventController::class, 'studentEvents'])->name('events.student');
        Route::apiResource('events', EventController::class);
        Route::post('/events/draft/store', [EventController::class, 'storeDraft'])->name('events.store-draft');
        Route::post('/events/{event}/publish', [EventController::class, 'publish'])->name('events.publish');
        Route::post('/events/{event}/unpublish', [EventController::class, 'unpublish'])->name('events.unpublish');
        Route::post('/events/{event}/complete', [EventController::class, 'complete'])->name('events.complete');

        // QR Configurations
        Route::get('/events/{event}/qr-configurations', [QrConfigurationController::class, 'index']);
        Route::get('/events/{event}/qr-configurations/last', [QrConfigurationController::class, 'last']);
        Route::post('/events/{event}/qr-configurations', [QrConfigurationController::class, 'store']);
        Route::put('/events/{event}/qr-configurations/{config}', [QrConfigurationController::class, 'update']);
        Route::post('/events/{event}/qr-configurations/{config}/generate', [QrConfigurationController::class, 'generate']);
        Route::delete('/events/{event}/qr-configurations/{config}', [QrConfigurationController::class, 'destroy']);

        // Attendance
        Route::get('/attendance/student-stats', [AttendanceController::class, 'studentStats'])->name('attendance.student-stats');
        Route::get('/attendance/events', [AttendanceController::class, 'studentEvents'])->name('attendance.events');
        Route::post('/attendance/scan', [AttendanceController::class, 'scan'])->name('attendance.scan');
        Route::post('/attendance/sync', [AttendanceController::class, 'sync'])->name('attendance.sync');
        Route::get('/attendance/history', [AttendanceController::class, 'history'])->name('attendance.history');
        Route::get('/events/{event}/attendance/export', [AttendanceController::class, 'exportEvent'])->name('attendance.export');

        // Fees
        Route::get('/fees/my', [FeeController::class, 'index'])->name('fees.my');
        Route::get('/fees/my/penalties', [FeeController::class, 'penalties'])->name('fees.my.penalties');
        Route::apiResource('fees', FeeController::class)->except(['index']);

        // Payments
        Route::get('/payments/outstanding', [PaymentController::class, 'outstanding'])->name('payments.outstanding');
        Route::get('/payments/submissions', [PaymentController::class, 'submissions'])->name('payments.submissions');
        Route::post('/payments/submissions', [PaymentController::class, 'submit'])->name('payments.submit');
        Route::get('/payments/submissions/{groupKey}', [PaymentController::class, 'submissionDetail'])->name('payments.submissions.show');
        Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
        Route::get('/payments/{payment}/receipt', [ReceiptController::class, 'paymentReceipt'])->name('payments.receipt');

        // Receipts
        Route::apiResource('receipts', ReceiptController::class)->only(['index', 'show']);

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
        Route::put('/notifications/push-token', [NotificationController::class, 'updatePushToken'])->name('notifications.push-token');
        Route::delete('/notifications/push-subscription', [NotificationController::class, 'removePushSubscription'])->name('notifications.push-subscription');

        // Reports
        Route::get('/reports/attendance', [ReportController::class, 'attendance'])->name('reports.attendance');
        Route::get('/reports/financial', [ReportController::class, 'financial'])->name('reports.financial');
        Route::get('/reports/penalty', [ReportController::class, 'penalty'])->name('reports.penalty');

        // Audit Logs
        Route::apiResource('audit-logs', AuditLogController::class)->only(['index', 'show']);

        // Face enrollment — descriptor only, encrypted at rest.
        Route::get('/face/enrollment', [FaceController::class, 'index'])->name('face.index');
        Route::post('/face/enroll', [FaceController::class, 'enroll'])->name('face.enroll');
        Route::delete('/face/enrollment', [FaceController::class, 'destroy'])->name('face.destroy');

        // One-device binding + transfers (device fingerprint rides the
        // X-Device-Fingerprint header).
        Route::get('/device/status', [DeviceController::class, 'status'])->name('device.status');
        Route::post('/devices/bind', [DeviceController::class, 'bind'])->name('device.bind');
        Route::post('/devices/transfer/request', [DeviceController::class, 'transferRequest'])->name('device.transfer-request');
        Route::get('/devices/transfer/requests', [DeviceController::class, 'transferRequests'])->name('device.transfer-requests');
        Route::post('/devices/transfer/requests/{transfer}/approve', [DeviceController::class, 'approve'])->name('device.transfer-approve');
        Route::post('/devices/transfer/requests/{transfer}/reject', [DeviceController::class, 'reject'])->name('device.transfer-reject');
    });
});

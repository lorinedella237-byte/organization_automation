<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : view('landing'))->name('landing');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');

    // Password reset. Added by Claude on 2026-10-28 while Codex was unavailable;
    // see the cross-lane claim in claude_codex.md under P1.03. The broker applies
    // its own per-address interval; these IP limits sit on top of it.
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:6,1')->name('password.store');
});

Route::middleware(['auth', 'auth.session'])->group(function () {
    Route::post('/platform/organizations', [\App\Http\Controllers\PlatformOrganizationController::class, 'store'])->middleware(['verified', 'throttle:6,1'])->name('platform.organizations.store');
    Route::get('/platform/organizations/create', [\App\Http\Controllers\PlatformOrganizationController::class, 'create'])->middleware(['verified', 'throttle:30,1'])->name('platform.organizations.create');
    Route::get('/verify-email', [\App\Http\Controllers\Auth\EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::post('/email/verification-notification', [\App\Http\Controllers\Auth\EmailVerificationController::class, 'send'])->middleware('throttle:6,1')->name('verification.send');
    Route::get('/verify-email/{id}/{hash}', [\App\Http\Controllers\Auth\EmailVerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::get('/home', [\App\Http\Controllers\DashboardController::class, 'show'])->middleware('workspace:0')->name('dashboard');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::post('/workspace/switch', [\App\Http\Controllers\WorkspaceController::class, 'switch'])->name('workspace.switch');
    Route::post('/security/2fa/begin', [\App\Http\Controllers\TwoFactorController::class, 'begin'])->name('security.2fa.begin');
    Route::post('/security/2fa/confirm', [\App\Http\Controllers\TwoFactorController::class, 'confirm'])->name('security.2fa.confirm');
});

Route::get('/invitations/{token}', [\App\Http\Controllers\InvitationController::class, 'show'])->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:30,1')->name('invitations.show');
Route::post('/invitations/accept', [\App\Http\Controllers\InvitationController::class, 'accept'])->middleware(['auth.session', 'throttle:6,1'])->name('invitations.accept');

Route::prefix('workspace')->middleware(['auth', 'auth.session', 'verified', 'workspace', '2fa.privileged'])->group(function () {
    Route::get('dashboard', [\App\Http\Controllers\DashboardController::class, 'show'])->name('workspace.dashboard');
    Route::get('documents', [\App\Http\Controllers\DocumentController::class, 'index'])->name('documents.index');
    Route::post('documents', [\App\Http\Controllers\DocumentController::class, 'store'])->name('documents.store');
    Route::get('documents/{document}/download', [\App\Http\Controllers\DocumentController::class, 'download'])->whereNumber('document')->name('documents.download');
    Route::post('documents/{document}/shares', [\App\Http\Controllers\DocumentController::class, 'share'])->whereNumber('document')->name('documents.shares.store');
    Route::post('folders', [\App\Http\Controllers\DocumentController::class, 'folder'])->name('folders.store');
    Route::post('documents/{document}/trash', [\App\Http\Controllers\DocumentController::class, 'trash'])->whereNumber('document')->name('documents.trash');
    Route::post('documents/{document}/restore', [\App\Http\Controllers\DocumentController::class, 'restore'])->whereNumber('document')->name('documents.restore');
    Route::post('documents/{document}/links', [\App\Http\Controllers\DocumentController::class, 'link'])->whereNumber('document')->name('documents.links.store');
    Route::get('reports', [\App\Http\Controllers\ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/export.csv', [\App\Http\Controllers\ReportController::class, 'export'])->name('reports.export');
    Route::post('reports/{report}/submit', [\App\Http\Controllers\ReportController::class, 'submit'])->whereNumber('report')->name('reports.submit');
    Route::post('reports/{report}/review', [\App\Http\Controllers\ReportController::class, 'review'])->whereNumber('report')->name('reports.review');
    Route::get('reports/{report}.pdf', [\App\Http\Controllers\ReportController::class, 'pdf'])->whereNumber('report')->name('reports.pdf');
    Route::get('attendance', [\App\Http\Controllers\AttendanceController::class, 'index'])->name('attendance.index');
    Route::get('work-calendars', [\App\Http\Controllers\WorkCalendarController::class, 'index'])->name('work-calendars.index');
    Route::post('work-calendars', [\App\Http\Controllers\WorkCalendarController::class, 'store'])->name('work-calendars.store');
    Route::post('work-calendars/holidays', [\App\Http\Controllers\WorkCalendarController::class, 'holiday'])->name('work-calendars.holidays.store');
    Route::post('attendance/clock-in', [\App\Http\Controllers\AttendanceController::class, 'clockIn'])->name('attendance.clock-in');
    Route::post('attendance/clock-out', [\App\Http\Controllers\AttendanceController::class, 'clockOut'])->name('attendance.clock-out');
    Route::post('attendance/corrections', [\App\Http\Controllers\AttendanceController::class, 'correct'])->name('attendance.corrections.store');
    Route::post('attendance/corrections/{correction}/decision', [\App\Http\Controllers\AttendanceController::class, 'decide'])->whereNumber('correction')->name('attendance.corrections.decision');
    Route::get('attendance/summary', [\App\Http\Controllers\AttendanceController::class, 'summary'])->name('attendance.summary');
    Route::get('attendance/register', [\App\Http\Controllers\AttendanceController::class, 'register'])->name('attendance.register');
    Route::get('attendance/export.csv', [\App\Http\Controllers\AttendanceController::class, 'export'])->name('attendance.export');
    Route::get('leave', [\App\Http\Controllers\LeaveController::class, 'index'])->name('leave.index');
    Route::post('leave', [\App\Http\Controllers\LeaveController::class, 'store'])->name('leave.store');
    Route::post('leave/{request}/decision', [\App\Http\Controllers\LeaveController::class, 'decide'])->whereNumber('request')->name('leave.decision');
    Route::post('leave/{request}/cancel', [\App\Http\Controllers\LeaveController::class, 'cancel'])->whereNumber('request')->name('leave.cancel');
    Route::get('payroll', [\App\Http\Controllers\PayrollController::class, 'index'])->name('payroll.index');
    Route::post('payroll/generate', [\App\Http\Controllers\PayrollController::class, 'generate'])->name('payroll.generate');
    Route::post('payroll/{period}/approve', [\App\Http\Controllers\PayrollController::class, 'approve'])->whereNumber('period')->name('payroll.approve');
    Route::post('payroll/{period}/submit', [\App\Http\Controllers\PayrollController::class, 'submit'])->whereNumber('period')->name('payroll.submit');
    Route::post('payroll/{period}/return', [\App\Http\Controllers\PayrollController::class, 'return'])->whereNumber('period')->name('payroll.return');
    Route::post('payroll/{period}/lock', [\App\Http\Controllers\PayrollController::class, 'lock'])->whereNumber('period')->name('payroll.lock');
    Route::post('payroll/lines/{line}/payments', [\App\Http\Controllers\PayrollController::class, 'payment'])->whereNumber('line')->name('payroll.payments.store');
    Route::get('payroll/lines/{line}/payslip.pdf', [\App\Http\Controllers\PayrollController::class, 'payslip'])->whereNumber('line')->name('payroll.payslip');
    Route::get('payroll/export.csv', [\App\Http\Controllers\PayrollController::class, 'export'])->name('payroll.export');
    Route::get('projects', [\App\Http\Controllers\ProjectController::class, 'index'])->name('projects.index');
    Route::get('projects/export.csv', [\App\Http\Controllers\ProjectController::class, 'export'])->name('projects.export');
    Route::get('tasks/my', [\App\Http\Controllers\ProjectController::class, 'myTasks'])->name('tasks.my');
    Route::post('projects', [\App\Http\Controllers\ProjectController::class, 'store'])->name('projects.store');
    Route::get('projects/{project}', [\App\Http\Controllers\ProjectController::class, 'show'])->whereNumber('project')->name('projects.show');
    Route::patch('projects/{project}', [\App\Http\Controllers\ProjectController::class, 'update'])->whereNumber('project')->name('projects.update');
    Route::post('projects/{project}/tasks', [\App\Http\Controllers\ProjectController::class, 'task'])->whereNumber('project')->name('projects.tasks.store');
    Route::patch('tasks/{task}', [\App\Http\Controllers\ProjectController::class, 'updateTask'])->whereNumber('task')->name('tasks.update');
    Route::post('tasks/{task}/comments', [\App\Http\Controllers\ProjectController::class, 'comment'])->whereNumber('task')->name('tasks.comments.store');
    Route::post('tasks/{task}/checklist', [\App\Http\Controllers\ProjectController::class, 'checklist'])->whereNumber('task')->name('tasks.checklist.store');
    Route::put('tasks/{task}/checklist/{item}', [\App\Http\Controllers\ProjectController::class, 'checklist'])->whereNumber(['task', 'item'])->name('tasks.checklist.update');
    Route::post('projects/{project}/members', [\App\Http\Controllers\ProjectController::class, 'member'])->whereNumber('project')->name('projects.members.store');
    Route::get('activities', [\App\Http\Controllers\ProjectController::class, 'activities'])->name('activities.index');
    Route::post('activities', [\App\Http\Controllers\ProjectController::class, 'saveActivity'])->name('activities.store');
    Route::put('activities/{activity}', [\App\Http\Controllers\ProjectController::class, 'saveActivity'])->whereNumber('activity')->name('activities.update');
    Route::get('work-logs', [\App\Http\Controllers\ProjectController::class, 'workLogs'])->name('work-logs.index');
    Route::post('work-logs', [\App\Http\Controllers\ProjectController::class, 'saveWorkLog'])->name('work-logs.store');
    Route::put('work-logs/{log}', [\App\Http\Controllers\ProjectController::class, 'saveWorkLog'])->whereNumber('log')->name('work-logs.update');
    Route::post('work-logs/{log}/approve', [\App\Http\Controllers\ProjectController::class, 'approveWorkLog'])->whereNumber('log')->name('work-logs.approve');
    Route::get('invitations', [\App\Http\Controllers\InvitationController::class, 'index'])->name('invitations.index');
    Route::post('invitations', [\App\Http\Controllers\InvitationController::class, 'store'])->name('invitations.store');
    Route::delete('invitations/{invitation}', [\App\Http\Controllers\InvitationController::class, 'revoke'])->whereNumber('invitation')->name('invitations.revoke');
    Route::get('notifications', [\App\Http\Controllers\WorkspaceNotificationController::class, 'index'])->name('notifications.index');
    Route::get('announcements', [\App\Http\Controllers\AnnouncementController::class, 'index'])->name('announcements.index');
    Route::post('announcements', [\App\Http\Controllers\AnnouncementController::class, 'store'])->name('announcements.store');
    Route::post('announcements/{announcement}/publish', [\App\Http\Controllers\AnnouncementController::class, 'publish'])->whereNumber('announcement')->name('announcements.publish');
    Route::post('notifications/{notification}/read', [\App\Http\Controllers\WorkspaceNotificationController::class, 'read'])->whereUuid('notification')->name('notifications.read');
    Route::get('settings', [\App\Http\Controllers\WorkspaceController::class, 'show'])->name('settings.organization');
    Route::patch('settings', [\App\Http\Controllers\WorkspaceController::class, 'update'])->name('settings.organization.update');
    Route::get('staff', [\App\Http\Controllers\StaffController::class, 'index'])->name('staff.index');
    Route::get('staff/{membership}', [\App\Http\Controllers\StaffController::class, 'show'])->whereNumber('membership')->name('staff.show');
    Route::patch('staff/{membership}', [\App\Http\Controllers\StaffController::class, 'update'])->whereNumber('membership')->name('staff.update');
    Route::post('staff/{membership}/status', [\App\Http\Controllers\StaffController::class, 'status'])->whereNumber('membership')->name('staff.status');
    Route::put('staff/{membership}/roles', [\App\Http\Controllers\StaffController::class, 'roles'])->whereNumber('membership')->name('staff.roles');
    Route::get('roles', [\App\Http\Controllers\RoleController::class, 'index'])->name('roles.index');
    Route::post('roles', [\App\Http\Controllers\RoleController::class, 'save'])->name('roles.store');
    Route::put('roles/{role}', [\App\Http\Controllers\RoleController::class, 'save'])->whereNumber('role')->name('roles.update');
    foreach (['departments', 'teams'] as $kind) {
        Route::get($kind, [\App\Http\Controllers\OrganizationStructureController::class, 'handle'])->defaults('kind', $kind)->name($kind.'.index');
        Route::post($kind, [\App\Http\Controllers\OrganizationStructureController::class, 'handle'])->defaults('kind', $kind)->name($kind.'.store');
    }
    Route::get('audit', [\App\Http\Controllers\AuditController::class, 'index'])->name('audit.index');
});

/*
|--------------------------------------------------------------------------
| P0.05 shell verification fixture. Claude, 2026-10-26
|--------------------------------------------------------------------------
|
| Local-only route so the shared Blade shell and component kit can be reviewed
| in a browser before real pages exist. Added as a coordinated exception to a
| Codex-owned file at the user's request; logged in claude_codex.md Section 9.
|
| Remove this block together with resources/views/dev/shell.blade.php once real
| pages extend layouts.app.
|
*/
if (app()->isLocal()) {
    Route::prefix('dev')->name('dev.')->group(function () {
        Route::get('/', fn () => view('dev.index', ['navPreview' => true]))->name('index');

        Route::get('shell', fn () => view('dev.shell', ['navPreview' => true]))->name('shell');

        // Guest-shell pages. Rendered standalone; the real routes and
        // controllers arrive with P1.03.
        Route::get('auth/login', fn () => view('auth.login'))->name('auth.login');
        Route::get('auth/forgot-password', fn () => view('auth.forgot-password'))->name('auth.forgot');

        Route::get('auth/reset-password', fn () => view('auth.reset-password', [
            'token' => 'sample-reset-token',
            'email' => 'a.mensah@example.org',
        ]))->name('auth.reset');

        Route::get('auth/accept-invitation', fn () => view('auth.accept-invitation', [
            'token' => 'sample-invitation-token',
            // Sample shape only. The real object arrives with the P1.03 contract.
            'invitation' => (object) [
                'organization_name' => 'Lorine Pilot Organization',
                'role_name' => 'Staff',
                'email' => 'new.member@example.org',
                'expires_at' => now()->addDays(7),
            ],
        ]))->name('auth.invite');

        Route::get('errors', fn () => view('dev.errors'))->name('errors');

        Route::get('errors/{code}', function (string $code) {
            abort_unless(in_array($code, ['403', '404', '419', '429', '500', '503'], true), 404);

            // Render the error view directly rather than aborting, so the page
            // itself can be reviewed without tripping the exception handler.
            return response()->view("errors.{$code}", [], (int) $code);
        })->name('errors.show');
    });
}

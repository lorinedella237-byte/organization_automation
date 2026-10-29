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
    Route::get('/verify-email', [\App\Http\Controllers\Auth\EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::post('/email/verification-notification', [\App\Http\Controllers\Auth\EmailVerificationController::class, 'send'])->middleware('throttle:6,1')->name('verification.send');
    Route::get('/verify-email/{id}/{hash}', [\App\Http\Controllers\Auth\EmailVerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::view('/home', 'dashboard')->middleware('workspace:0')->name('dashboard');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::post('/workspace/switch', [\App\Http\Controllers\WorkspaceController::class, 'switch'])->name('workspace.switch');
});

Route::get('/invitations/{token}', [\App\Http\Controllers\InvitationController::class, 'show'])->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:30,1')->name('invitations.show');
Route::post('/invitations/accept', [\App\Http\Controllers\InvitationController::class, 'accept'])->middleware(['auth.session', 'throttle:6,1'])->name('invitations.accept');

Route::prefix('workspace')->middleware(['auth', 'auth.session', 'verified', 'workspace'])->group(function () {
    Route::get('invitations', [\App\Http\Controllers\InvitationController::class, 'index'])->name('invitations.index');
    Route::post('invitations', [\App\Http\Controllers\InvitationController::class, 'store'])->name('invitations.store');
    Route::delete('invitations/{invitation}', [\App\Http\Controllers\InvitationController::class, 'revoke'])->whereNumber('invitation')->name('invitations.revoke');
    Route::get('notifications', [\App\Http\Controllers\WorkspaceNotificationController::class, 'index'])->name('notifications.index');
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

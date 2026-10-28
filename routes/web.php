<?php

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

Route::get('/', fn () => view('landing'))->name('landing');

Route::get('/home', function(){
    return "welcome simon";
    }
);

/*
|--------------------------------------------------------------------------
| P0.05 shell verification fixture — Claude, 2026-10-26
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
            // Sample shape only — the real object arrives with the P1.03 contract.
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

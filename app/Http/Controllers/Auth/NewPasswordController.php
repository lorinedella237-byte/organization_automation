<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\NewPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class NewPasswordController extends Controller
{
    public function create(Request $request, string $token): View
    {
        // The address is carried in the link so the field can be shown read-only,
        // but it is never trusted on its own: the broker checks the token
        // against the hashed record stored for that exact address, so a tampered
        // address simply fails to match.
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function store(NewPasswordRequest $request): RedirectResponse
    {
        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                // `password` is cast as `hashed` on the model, so the plain value
                // is hashed on save; it must not be hashed again here.
                //
                // Rotating the remember token signs out every "remember me"
                // session on other devices. Someone resetting a password may be
                // doing it because they lost control of the account, so leaving
                // those cookies valid would defeat the reset.
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            // Reaching here means the token was wrong, expired, already used, or
            // issued for a different address. The message is attached to the
            // email field because that is where the form shows it, and the
            // broker's own strings do not distinguish the cases.
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        // The new password is not used to sign the visitor in automatically.
        // They prove they hold it by entering it once, which also means a reset
        // link that was read by someone else does not hand over a live session.
        return redirect()->route('login')->with('status', __('Your password has been reset. Sign in with your new password.'));
    }
}

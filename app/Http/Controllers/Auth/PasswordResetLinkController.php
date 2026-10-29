<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasswordResetLinkRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(PasswordResetLinkRequest $request): RedirectResponse
    {
        // The broker's result is deliberately discarded. A visitor must not be
        // able to tell an unknown address from a known one, from a throttled
        // one, because that turns this form into a membership oracle: accounts
        // here are created by invitation, so confirming an address exists
        // confirms someone works for the organization.
        //
        // Throttling still applies. The broker enforces its own per-address
        // interval (config/auth.php `passwords.users.throttle`), and the route
        // adds an IP limit. A throttled caller simply receives no new mail and
        // the same message as everyone else.
        Password::broker()->sendResetLink($request->only('email'));

        return back()->with('status', __('If that address belongs to an account, a password reset link is on its way. The link expires in one hour.'));
    }
}

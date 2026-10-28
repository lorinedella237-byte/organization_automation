{{--
    Sign in. P1.03-FE.

    Posts to the `login` route. There is no "create an account" link: access is
    invitation-first and public registration is outside Release 1.
--}}
@extends('layouts.guest')

@section('title', __('Sign in'))
@section('subtitle', __('Sign in to continue'))

@section('content')
    <form method="POST" action="{{ Route::has('login') ? route('login') : '' }}" novalidate>
        @csrf

        <x-input
            name="email"
            type="email"
            :label="__('Work email')"
            autocomplete="username"
            autofocus
            required />

        <x-input
            name="password"
            type="password"
            :label="__('Password')"
            autocomplete="current-password"
            required />

        <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" name="remember" id="remember"
                       @checked(old('remember'))>
                <label class="form-check-label" for="remember">{{ __('Remember me') }}</label>
            </div>

            @if (Route::has('password.request'))
                <a class="small text-decoration-none" href="{{ route('password.request') }}">
                    {{ __('Forgot password?') }}
                </a>
            @endif
        </div>

        <button type="submit" class="btn btn-primary w-100">{{ __('Sign in') }}</button>
    </form>
@endsection

@section('below-card')
    <span class="text-secondary">
        {{ __('Accounts are created by invitation. Contact your organization administrator for access.') }}
    </span>
@endsection

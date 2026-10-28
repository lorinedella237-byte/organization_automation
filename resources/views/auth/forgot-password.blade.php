{{--
    Request a password reset link. P1.03-FE.

    The confirmation message must be identical whether or not the address
    exists, so this page cannot be used to discover who has an account. The
    copy below is written to stay truthful in both cases.
--}}
@extends('layouts.guest')

@section('title', __('Reset your password'))
@section('subtitle', __('We will email you a reset link'))

@section('content')
    <p class="text-secondary small">
        {{ __('Enter the email address on your account. If it matches an active account, we will send a link to choose a new password.') }}
    </p>

    <form method="POST" action="{{ Route::has('password.email') ? route('password.email') : '' }}" novalidate>
        @csrf

        <x-input
            name="email"
            type="email"
            :label="__('Work email')"
            autocomplete="username"
            autofocus
            required />

        <button type="submit" class="btn btn-primary w-100">{{ __('Send reset link') }}</button>
    </form>
@endsection

@section('below-card')
    @if (Route::has('login'))
        <a class="text-decoration-none" href="{{ route('login') }}">{{ __('Back to sign in') }}</a>
    @endif
@endsection

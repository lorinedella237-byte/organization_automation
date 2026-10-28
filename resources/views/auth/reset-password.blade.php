{{--
    Choose a new password from an emailed reset link. P1.03-FE.

    Expects $token from the signed link and $email from the query string. The
    email is submitted as a readonly field so the user can see which account
    they are changing; the server must still validate it against the token
    rather than trusting this input.
--}}
@extends('layouts.guest')

@section('title', __('Choose a new password'))
@section('subtitle', __('Set the password for your account'))

@section('content')
    <form method="POST" action="{{ Route::has('password.store') ? route('password.store') : '' }}" novalidate>
        @csrf

        <input type="hidden" name="token" value="{{ $token ?? request('token') }}">

        <x-input
            name="email"
            type="email"
            :label="__('Work email')"
            :value="$email ?? request('email')"
            autocomplete="username"
            readonly
            required />

        <x-input
            name="password"
            type="password"
            :label="__('New password')"
            :help="__('Use at least 8 characters. A longer passphrase is stronger than a short, complex one.')"
            autocomplete="new-password"
            autofocus
            required />

        <x-input
            name="password_confirmation"
            type="password"
            :label="__('Confirm new password')"
            autocomplete="new-password"
            required />

        <button type="submit" class="btn btn-primary w-100">{{ __('Save new password') }}</button>
    </form>
@endsection

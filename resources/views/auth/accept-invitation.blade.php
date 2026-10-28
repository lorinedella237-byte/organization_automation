{{--
    Accept an organization invitation and set up the account. P1.03-FE.

    This is the only path to a new account, so it is the first screen a new
    member of staff ever sees. The organization and the invited role are shown
    read-only, because they were decided by whoever sent the invitation, and the
    invitee must never be able to choose or change them here.

    Expected view data (P1.03 contract): $invitation with ->organization_name,
    ->role_name, ->email, ->expires_at, and $token.
--}}
@extends('layouts.guest')

@section('title', __('Accept invitation'))
@section('subtitle', __('Set up your account'))

@section('content')
    @if (isset($invitation))
        <div class="bg-body-tertiary border rounded p-3 mb-4 small">
            <div class="d-flex justify-content-between gap-2">
                <span class="text-secondary">{{ __('Organization') }}</span>
                <span class="fw-medium text-end">{{ $invitation->organization_name }}</span>
            </div>
            <div class="d-flex justify-content-between gap-2 mt-1">
                <span class="text-secondary">{{ __('Role') }}</span>
                <span class="fw-medium text-end">{{ $invitation->role_name }}</span>
            </div>
            <div class="d-flex justify-content-between gap-2 mt-1">
                <span class="text-secondary">{{ __('Email') }}</span>
                <span class="fw-medium text-end text-break">{{ $invitation->email }}</span>
            </div>

            @if (! empty($invitation->expires_at))
                <hr class="my-2">
                <p class="text-secondary mb-0">
                    {{ __('This invitation expires :date.', ['date' => $invitation->expires_at->format('j M Y, H:i')]) }}
                </p>
            @endif
        </div>
    @endif

    <form method="POST" action="{{ Route::has('invitations.accept') ? route('invitations.accept') : '' }}" novalidate>
        @csrf

        <input type="hidden" name="token" value="{{ $token ?? request('token') }}">

        <div class="row g-2">
            <div class="col-12 col-sm-6">
                <x-input name="first_name" :label="__('First name')" autocomplete="given-name" autofocus required />
            </div>
            <div class="col-12 col-sm-6">
                <x-input name="last_name" :label="__('Last name')" autocomplete="family-name" required />
            </div>
        </div>

        <x-input name="middle_name" :label="__('Middle name')" autocomplete="additional-name" />

        <x-input name="phone" type="tel" :label="__('Phone')" autocomplete="tel" required />

        <x-input
            name="password"
            type="password"
            :label="__('Create a password')"
            :help="__('Use at least 8 characters. A longer passphrase is stronger than a short, complex one.')"
            autocomplete="new-password"
            required />

        <x-input
            name="password_confirmation"
            type="password"
            :label="__('Confirm password')"
            autocomplete="new-password"
            required />

        <button type="submit" class="btn btn-primary w-100">{{ __('Create account') }}</button>
    </form>
@endsection

@section('below-card')
    <span class="text-secondary">
        {{ __('By creating an account you agree to your organization\'s acceptable use policy.') }}
    </span>
@endsection

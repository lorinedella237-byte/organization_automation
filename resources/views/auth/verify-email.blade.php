@extends('layouts.guest')
@section('title', __('Verify your email'))
@section('subtitle', __('Verify your email to access your workspace'))
@section('content')
    {{-- Naming the address matters: someone with several addresses cannot act
         on "your email address" without knowing which one was used. --}}
    <p>
        {{ __('Open the verification link sent to') }}
        <span class="font-monospace">{{ auth()->user()->email }}</span>.
        {{ __('You can request another link below.') }}
    </p>
    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button type="submit" class="btn btn-primary w-100">{{ __('Send verification link') }}</button>
    </form>
    <form method="POST" action="{{ route('logout') }}" class="mt-3">
        @csrf
        <button type="submit" class="btn btn-outline-secondary w-100">{{ __('Sign out') }}</button>
    </form>
@endsection

@extends('layouts.app')

@section('title', __('Home'))

@section('content')
    <h1 class="h3 mb-3">{{ __('Welcome, :name', ['name' => auth()->user()->name]) }}</h1>
    <div class="card">
        <div class="card-body">
            <h2 class="h5">{{ __('Your account') }}</h2>
            <p>{{ __('You are signed in.') }}</p>
            <p class="text-secondary">{{ auth()->user()->email }}</p>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-outline-secondary">{{ __('Sign out') }}</button>
            </form>
        </div>
    </div>
@endsection

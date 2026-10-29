{{--
    A signed-in person who lands here has usually followed a link to something
    their role does not cover, and the application shell is not rendered on an
    error page. Without a way back they are stranded on a dead page.

    Sign out is offered because one 403 cannot be escaped by going to the
    dashboard: when a membership is suspended or removed, ResolveWorkspace
    denies every workspace page including the dashboard, and the message tells
    the reader to sign out. That control has to exist for them to follow it.
--}}
@php($sessionSafe = true)
@extends('errors.layout')

@section('code', '403')
@section('title', __('Access denied'))
@section('message', __('You do not have permission to view this page. If you believe you should, ask an administrator of your organization to review your role.'))

@section('escape')
    <form method="POST" action="{{ url('/logout') }}">
        @csrf
        <button type="submit" class="quiet">{{ __('Sign out') }}</button>
    </form>
@endsection

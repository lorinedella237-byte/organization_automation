{{--
    Session aware for the same reason as 403: a stale or mistyped workspace URL
    should return the reader to the dashboard, not to the public landing page.
--}}
@php($sessionSafe = true)
@extends('errors.layout')

@section('code', '404')
@section('title', __('Page not found'))
@section('message', __('The page you requested does not exist, or it may have been moved or deleted.'))

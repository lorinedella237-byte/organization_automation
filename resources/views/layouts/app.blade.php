{{--
    Authenticated application shell.

    Controllers extend this and fill @section('content'). Available sections:
      title        page title fragment (appended to the app name)
      page-header  optional; replaces the default <x-page-header> slot
      content      required
      scripts      pushed to @stack('scripts') at end of body

    Everything shown here is presentation only. Visibility of a nav item or a
    button is never a substitute for server-side authorization.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@hasSection('title')@yield('title') &middot; @endif{{ config('app.name') }}</title>

    <link rel="icon" href="{{ asset('favicon.ico') }}">

    @include('layouts.partials.fonts')

    @vite(['resources/css/app.scss', 'resources/js/app.js'])
</head>
<body>
    <a class="skip-link" href="#main-content">{{ __('Skip to main content') }}</a>

    <div class="app-shell">
        @include('layouts.partials.sidebar')

        <div class="app-sidebar-backdrop" data-sidebar-backdrop hidden></div>

        <div class="app-main">
            @include('layouts.partials.topbar')

            <main class="app-content" id="main-content" tabindex="-1">
                @include('layouts.partials.flash')

                @hasSection('page-header')
                    @yield('page-header')
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    @stack('scripts')
</body>
</html>

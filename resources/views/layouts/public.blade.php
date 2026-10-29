{{--
    Public marketing shell, the only publicly reachable layout.

    Separate from `guest` (the bare card used for sign-in and invitation
    acceptance) because public pages need a nav and footer, and separate from
    `app` because there is no session, no workspace and no permission context
    here.

    The header and footer are ink bands that stay dark in both colour schemes,
    so they bracket the page consistently whichever theme the visitor prefers.

    Access is invitation-first, so the only call to action is Sign in. This
    layout must never gain a public registration path.

    Sections: title, description, content, and an optional `nav-extra`.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@hasSection('title')@yield('title') &middot; @endif{{ config('app.name') }}</title>
    <meta name="description" content="@yield('description', 'Organization management for staff reporting, projects, documents, attendance and payroll records.')">

    <link rel="icon" href="{{ asset('favicon.ico') }}">

    @include('layouts.partials.fonts')

    @vite(['resources/css/app.scss', 'resources/js/app.js'])
</head>
<body>
    <a class="skip-link" href="#main-content">{{ __('Skip to main content') }}</a>

    <header class="site-header">
        <div class="container">
            <nav class="d-flex align-items-center gap-3 py-3" aria-label="{{ __('Primary') }}">
                <a class="site-brand" href="{{ url('/') }}">
                    <span class="site-mark" aria-hidden="true"></span>
                    <span>{{ config('app.name') }}</span>
                </a>

                <div class="d-none d-md-flex align-items-center gap-1 ms-4">
                    <a class="site-nav-link" href="{{ url('/#modules') }}">{{ __('Modules') }}</a>
                    <a class="site-nav-link" href="{{ url('/#access') }}">{{ __('Access') }}</a>
                    <a class="site-nav-link" href="{{ url('/#workflow') }}">{{ __('Workflow') }}</a>
                </div>

                <div class="ms-auto d-flex align-items-center gap-2">
                    @yield('nav-extra')

                    <button type="button"
                            class="site-icon-btn"
                            data-theme-toggle
                            aria-pressed="false"
                            aria-label="{{ __('Toggle dark mode') }}">
                        <i class="bi bi-circle-half" aria-hidden="true"></i>
                    </button>

                    <a class="btn btn-sm btn-on-ink px-3"
                       href="{{ Route::has('login') ? route('login') : url('/dev/auth/login') }}">
                        {{ __('Sign in') }}
                    </a>
                </div>
            </nav>
        </div>
    </header>

    <main id="main-content">
        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="container py-5">
            <div class="row g-4">
                <div class="col-12 col-lg-5">
                    <a class="site-brand mb-3" href="{{ url('/') }}">
                        <span class="site-mark" aria-hidden="true"></span>
                        <span>{{ config('app.name') }}</span>
                    </a>
                    <p class="mb-0" style="max-width: 34ch;">
                        {{ __('Staff reporting, projects, documents, attendance and payroll records for one organization.') }}
                    </p>
                </div>

                <div class="col-6 col-lg-3">
                    <p class="site-footer__heading label-mono">{{ __('Product') }}</p>
                    <ul class="site-footer__list">
                        <li><a href="{{ url('/#modules') }}">{{ __('Modules') }}</a></li>
                        <li><a href="{{ url('/#access') }}">{{ __('Access and scope') }}</a></li>
                        <li><a href="{{ url('/#workflow') }}">{{ __('Report workflow') }}</a></li>
                    </ul>
                </div>

                <div class="col-6 col-lg-4">
                    <p class="site-footer__heading label-mono">{{ __('Access') }}</p>
                    <ul class="site-footer__list">
                        <li>
                            <a href="{{ Route::has('login') ? route('login') : url('/dev/auth/login') }}">
                                {{ __('Sign in') }}
                            </a>
                        </li>
                        <li>{{ __('Accounts are created by invitation.') }}</li>
                    </ul>
                </div>
            </div>

            <div class="site-footer__bottom d-flex flex-wrap justify-content-between gap-2">
                <span>&copy; {{ now()->year }} {{ config('app.name') }}. {{ __('All rights reserved.') }}</span>
                <span>{{ __('Internal system. Access is restricted to invited members.') }}</span>
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>

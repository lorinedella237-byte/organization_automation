{{--
    Guest shell: login, invitation acceptance and password reset.

    Deliberately has no navigation and no workspace context: a guest has no
    organization resolved yet. Access is invitation-first, so this layout must
    never grow a public "create an account" path.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@hasSection('title')@yield('title') &middot; @endif{{ config('app.name') }}</title>

    <link rel="icon" href="{{ asset('favicon.ico') }}">

    @vite(['resources/css/app.scss', 'resources/js/app.js'])
</head>
<body class="d-flex flex-column min-vh-100">
    <main class="flex-grow-1 d-flex align-items-center py-5" id="main-content">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-12 col-sm-10 col-md-7 col-lg-5 col-xl-4">
                    <div class="text-center mb-4">
                        <span class="h4 fw-semibold">{{ config('app.name') }}</span>
                        @hasSection('subtitle')
                            <p class="text-secondary small mb-0 mt-1">@yield('subtitle')</p>
                        @endif
                    </div>

                    <div class="card shadow-sm">
                        <div class="card-body p-4">
                            @include('layouts.partials.flash')

                            @yield('content')
                        </div>
                    </div>

                    @hasSection('below-card')
                        <div class="text-center mt-3 small">@yield('below-card')</div>
                    @endif
                </div>
            </div>
        </div>
    </main>

    <footer class="py-3 text-center text-secondary small">
        &copy; {{ now()->year }} {{ config('app.name') }}
    </footer>

    @stack('scripts')
</body>
</html>

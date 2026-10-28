{{--
    P0.05 / P1.03 preview index. NOT A PRODUCT PAGE.

    Lists the frontend pages built so far so they can be reviewed before the
    real routes and controllers exist. Local environment only.
    Delete with the rest of resources/views/dev/ once real pages are wired.
--}}
@extends('layouts.app')

@section('title', 'Frontend previews')

@section('content')
    <x-page-header
        title="Frontend previews"
        subtitle="Pages built so far. All content is hard-coded sample data." />

    <x-alert type="warning" class="mb-4">
        <strong>Preview environment.</strong> These pages are verification fixtures served only
        when <code>APP_ENV=local</code>. Nothing here reads the database, and the sidebar is
        showing every item because no permissions exist yet.
    </x-alert>

    <div class="row g-3">
        @foreach ([
            ['Shell &amp; component kit', 'dev.shell', 'The app shell with every component, status badge, table and form control.', 'layout-sidebar', 'P0.05-FE'],
            ['Sign in', 'dev.auth.login', 'Guest shell. Invitation-first, so no public sign-up link.', 'box-arrow-in-right', 'P1.03-FE'],
            ['Forgot password', 'dev.auth.forgot', 'Request a reset link. Copy stays truthful whether or not the account exists.', 'key', 'P1.03-FE'],
            ['Reset password', 'dev.auth.reset', 'Set a new password from an emailed link.', 'shield-lock', 'P1.03-FE'],
            ['Accept invitation', 'dev.auth.invite', 'The only route to a new account. Organization and role are read-only.', 'envelope-open', 'P1.03-FE'],
            ['Error pages', 'dev.errors', 'Self-contained 403/404/419/429/500/503 pages.', 'exclamation-triangle', 'P0.05-FE'],
        ] as [$title, $routeName, $description, $icon, $task])
            <div class="col-12 col-md-6 col-xl-4">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                            <span class="empty-state__icon mb-0" style="width:2.25rem;height:2.25rem">
                                <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>
                            </span>
                            <span class="badge text-bg-light font-monospace">{{ $task }}</span>
                        </div>

                        <p class="fw-semibold mb-1">{!! $title !!}</p>
                        <p class="text-secondary small flex-grow-1">{{ $description }}</p>

                        @if (Route::has($routeName))
                            <a href="{{ route($routeName) }}" class="btn btn-sm btn-outline-primary align-self-start">
                                {{ __('Open') }} <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
                            </a>
                        @else
                            <span class="btn btn-sm btn-outline-secondary disabled align-self-start">
                                {{ __('Not available') }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection

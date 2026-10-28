{{--
    Error page index. Preview fixture, local only.

    The error pages are deliberately self-contained (no @vite, no session, no
    query) so they still render when the app is broken. They therefore cannot
    be embedded in this shell; each opens on its own.
--}}
@extends('layouts.app')

@section('title', 'Error pages')

@section('content')
    <x-page-header
        title="Error pages"
        subtitle="Each renders standalone with inline CSS, with no assets, no session and no database"
        :breadcrumbs="['Previews' => route('dev.index'), 'Error pages' => null]" />

    <x-alert type="info" class="mb-4">
        These pages intentionally avoid <code>&#64;vite</code>, session reads and queries, so a 500 caused
        by a missing asset manifest or an unreachable database still renders. No exception
        message is ever shown to the user.
    </x-alert>

    <div class="row g-3">
        @foreach ([
            ['403', 'Access denied', 'Permission check failed.'],
            ['404', 'Page not found', 'Unknown or removed URL.'],
            ['419', 'Session expired', 'CSRF token expired after inactivity.'],
            ['429', 'Too many requests', 'Rate limit reached.'],
            ['500', 'Something went wrong', 'Unhandled server error.'],
            ['503', 'Under maintenance', 'Application is in maintenance mode.'],
        ] as [$code, $title, $when])
            <div class="col-6 col-md-4 col-xl-2">
                <a href="{{ route('dev.errors.show', $code) }}"
                   class="card h-100 text-decoration-none text-body">
                    <div class="card-body text-center">
                        <p class="h4 fw-semibold mb-1">{{ $code }}</p>
                        <p class="small fw-medium mb-1">{{ $title }}</p>
                        <p class="text-secondary mb-0" style="font-size:.75rem">{{ $when }}</p>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
@endsection

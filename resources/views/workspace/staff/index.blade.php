{{--
    Staff directory. P1.04-FE.

    Rows come from StaffController@index, which has already applied tenancy and
    the viewer's staff.view scope, so this template renders exactly what the
    server decided the viewer may see. It never widens that set and never
    assumes the viewer is an administrator.

    The list is deliberately a register rather than a grid of cards: it is the
    organization's roll, and it is read by scanning down a column.
--}}
@extends('layouts.app')

@section('title', __('Staff'))

@section('content')
    <x-page-header
        :title="__('Staff')"
        :subtitle="__('Everyone you have permission to see in this workspace.')"
        :breadcrumbs="[__('Workspace') => null, __('Staff') => null]">
        <x-slot:actions>
            @can('staff.invite')
                {{-- Invitations are not implemented yet. The control stays out
                     of the page entirely rather than appearing and failing. --}}
                @if (Route::has('invitations.create'))
                    <a class="btn btn-primary btn-sm" href="{{ route('invitations.create') }}">
                        <i class="bi bi-person-plus me-1" aria-hidden="true"></i>{{ __('Invite someone') }}
                    </a>
                @endif
            @endcan
        </x-slot:actions>
    </x-page-header>

    <form method="GET" action="{{ route('staff.index') }}" class="row g-2 align-items-end mb-3">
        <div class="col-12 col-sm-6 col-lg-4">
            <x-input
                name="search"
                :label="__('Search')"
                :value="$search"
                :help="__('Matches first name, last name or email.')" />
        </div>
        <div class="col-auto mb-3">
            <button type="submit" class="btn btn-outline-secondary">{{ __('Search') }}</button>
        </div>
        @if (filled($search))
            <div class="col-auto mb-3">
                <a class="btn btn-link px-1" href="{{ route('staff.index') }}">{{ __('Clear') }}</a>
            </div>
        @endif
    </form>

    @if ($page->total() === 0)
        {{-- P7.04: an empty filter result and an empty directory are different
             situations and must not share one message. --}}
        @if (filled($search))
            <x-empty-state
                icon="search"
                :title="__('No one matches that search')"
                :description="__('Try part of a name or an email address, or clear the search to see everyone in scope.')" />
        @else
            <x-empty-state
                icon="people"
                :title="__('No staff are visible to you yet')"
                :description="__('Once people are invited and placed in your scope, they appear here.')" />
        @endif
    @else
        <div class="table-responsive">
            <table class="table table-data align-middle">
                <caption class="visually-hidden">{{ __('Staff visible to you in this workspace') }}</caption>
                <thead>
                    <tr>
                        <th scope="col">{{ __('Name') }}</th>
                        <th scope="col">{{ __('Employee no.') }}</th>
                        <th scope="col">{{ __('Title') }}</th>
                        <th scope="col">{{ __('Started') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                        <th scope="col"><span class="visually-hidden">{{ __('Actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($page as $member)
                        <tr>
                            <td>
                                <span class="d-block fw-semibold">{{ $member['name'] }}</span>
                                <span class="text-secondary small">{{ $member['email'] }}</span>
                            </td>
                            <td class="font-monospace small">{{ $member['employee_number'] ?: __('Not set') }}</td>
                            <td>{{ $member['title'] ?: __('Not set') }}</td>
                            <td class="font-monospace small">{{ $member['start_date'] ?: __('Not set') }}</td>
                            <td><x-status-badge :status="$member['status']" /></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-secondary"
                                   href="{{ route('staff.show', $member['id']) }}">{{ __('Open') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <p class="text-secondary small mb-0">
                {{ __('Showing :from to :to of :total', [
                    'from' => $page->firstItem(),
                    'to' => $page->lastItem(),
                    'total' => $page->total(),
                ]) }}
            </p>
            {{ $page->withQueryString()->links() }}
        </div>
    @endif
@endsection
